<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Judge;

use Bhp\Api\Credit\ApiJury;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;

class JudgePlugin extends BasePlugin implements PluginTaskInterface
{
    private AuthFailureClassifier $authFailureClassifier;
    private ?ApiJury $juryApi = null;

    /**
     * 插件信息
     *
     * @var array<string, int|string>
     */

    /**
     * @var array<int, array{id: string, vote: int}>
     */
    protected array $wait_case = [];

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        $this->resetTaskResult();

        if (!$this->enabled('judge')) {
            return TaskResult::keepSchedule();
        }

        if (!$this->juryInfo()) {
            return $this->resolveTaskResult(TaskResult::after(mt_rand(60, 120) * 60));
        }

        $this->judgementTask();

        return $this->resolveTaskResult(TaskResult::after(mt_rand(15, 30) * 60));
    }

    protected function judgementTask(): void
    {
        if (empty($this->wait_case)) {
            $cid = $this->caseObtain();
            $this->caseCheck($cid);
            return;
        }

        $lastKey = array_key_last($this->wait_case);
        $case = $lastKey !== null ? $this->wait_case[$lastKey] : null;
        if ($case === null) {
            return;
        }

        if (!$this->vote($case['id'], $case['vote'])) {
            $this->scheduleAfter(60.0);

            return;
        }

        array_pop($this->wait_case);
    }

    protected function caseCheck(string $caseId): bool
    {
        if ($caseId === '') {
            return true;
        }
        $caseInfo = $this->caseInfo($caseId);
        if (empty($caseInfo)) {
            return true;
        }
        $caseOpinion = $this->caseOpinion($caseId);
        if (empty($caseOpinion)) {
            $voteInfo = $caseInfo[$this->probability()];
        } else {
            $voteInfo = $caseOpinion[array_rand($caseOpinion)];
        }

        $vote = $voteInfo['vote'];
        $voteText = $voteInfo['vote_text'];
        $this->wait_case[] = ['id' => $caseId, 'vote' => $vote];
        $this->info("風機委員: 案件{$caseId}的預測投票結果 {$vote}({$voteText})");
        $this->vote($caseId, 0);
        $this->scheduleAfter(65);

        return false;
    }

    private function vote(string $caseId, int $vote): bool
    {
        $response = $this->juryApi()->vote($caseId, $vote, '', 0, array_rand([0, 1]));
        $this->authFailureClassifier->assertNotAuthFailure($response, "風機委員: 案件{$caseId}投票时账号未登录");

        if ($response['code']) {
            $this->warning("風機委員: 案件{$caseId}投票失败 {$response['code']} -> {$response['message']}");
            return false;
        } else {
            $this->notice("風機委員: 案件{$caseId}投票成功");
            return true;
        }
    }

    protected function randInt(int $max = 17): string
    {
        $temp = [];
        foreach (range(1, $max) as $ignored) {
            $temp[] = mt_rand(0, 9);
        }

        return implode('', $temp);
    }

    protected function probability(): int
    {
        $result = 0;
        $prizeArr = [0 => 25, 1 => 40, 2 => 25, 3 => 10];
        $sum = array_sum($prizeArr);
        foreach ($prizeArr as $key => $value) {
            if (mt_rand(1, $sum) <= $value) {
                $result = $key;
                break;
            }
            $sum -= $value;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function caseOpinion(string $caseId): array
    {
        $response = $this->juryApi()->caseOpinion($caseId);
        $this->authFailureClassifier->assertNotAuthFailure($response, "風機委員: 获取案件{$caseId}众议观点时账号未登录");
        if ($response['code']) {
            $this->warning("風機委員: 獲取案件{$caseId}衆議觀點失敗 {$response['code']} -> {$response['message']}");

            return [];
        }
        if (is_null($response['data']['list']) || $response['data']['total'] == 0) {
            return [];
        }

        return $response['data']['list'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function caseInfo(string $caseId): array
    {
        $response = $this->juryApi()->caseInfo($caseId);
        $this->authFailureClassifier->assertNotAuthFailure($response, "風機委員: 获取案件{$caseId}详情时账号未登录");
        if ($response['code']) {
            $this->warning("風機委員: 獲取案件{$caseId}詳情失敗 {$response['code']} -> {$response['message']}");

            return [];
        }

        return $response['data']['vote_items'];
    }

    protected function caseObtain(): string
    {
        $response = $this->juryApi()->caseNext();
        $this->authFailureClassifier->assertNotAuthFailure($response, '風紀委員: 获取案例ID时账号未登录');

        switch ($response['code']) {
            case 0:
                $cid = $response['data']['case_id'];
                $this->info("風紀委員: 获取案例ID成功 {$cid} ~");
                return $cid;
            case 25005:
                $this->warning("風紀委員: 获取案例ID失敗 {$response['code']} -> {$response['message']}");
                $this->taskResult = TaskResult::nextAt(10);
                break;
            case 25006:
                $this->warning("風紀委員: 获取案例ID失敗 {$response['code']} -> {$response['message']}");
                $this->taskResult = TaskResult::nextAt(10);
                $this->notify('jury_leave_office', $response['message']);
                break;
            case 25008:
                $this->info("風紀委員: {$response['message']}");
                break;
            case 25014:
                $this->info('風紀委員: 今日案件已審滿，感謝您對社區的貢獻，明天再來看看吧~');
                $this->taskResult = TaskResult::nextAt(7, 0, 0, 1, 60);
                break;
            default:
                $this->warning("風紀委員: 获取案例ID失敗 {$response['code']} -> {$response['message']}");
                break;
        }

        return '';
    }

    protected function juryInfo(): bool
    {
        $response = $this->juryApi()->jury();
        $this->authFailureClassifier->assertNotAuthFailure($response, '風紀委員: 获取审判资格时账号未登录');
        if ($response['code']) {
            return false;
        }
        if ($response['data']['status'] == 1) {
            $this->info('風機委員: 當前可以參與社區衆裁，共創良好環境哦~');
            return true;
        }
        $data = $response['data'];
        if ($this->config('judge.auto_apply', false, 'bool') && $data['allow_apply']) {
            if ($data['apply_status'] == -1 || $data['apply_status'] == 4) {
                $this->juryApply();
            }
            if ($data['apply_status'] == 3 && $data['status'] == 2 && $data['err_msg'] == base64_decode('5bey5Y245Lu76aOO57qq5aeU5ZGY')) {
                $this->juryApply();
            }
        }

        $this->warning('風機委員: 當前沒有審判資格哦~');

        return false;
    }

    protected function juryApply(): void
    {
        $response = $this->juryApi()->juryApply();
        $this->authFailureClassifier->assertNotAuthFailure($response, '風機委員: 申请连任时账号未登录');
        if ($response['code']) {
            $this->warning("風機委員: 申請連任提交失敗 {$response['code']} -> {$response['message']}");
        } else {
            $this->notice('風機委員: 申請連任提交成功');
            $this->notify('jury_auto_apply', '提交連任申請成功');
        }
    }

    private function judgementIndex(): bool
    {
        $response = $this->juryApi()->caseList();
        $this->authFailureClassifier->assertNotAuthFailure($response, '風紀委員: 获取案例数据时账号未登录');
        if ($response['code']) {
            $this->info("風紀委員: 獲取案例數據失敗 {$response['code']} -> {$response['message']}");
            return false;
        }

        $data = $response['data'];
        $today = date('Y-m-d');
        $sumCases = 0;
        $validCases = 0;
        $judgingCases = 0;
        foreach ($data as $case) {
            $ts = $case['voteTime'] / 1000;
            $voteDay = date('Y-m-d', $ts);
            if ($voteDay == $today) {
                $sumCases += 1;
                $vote = $case['vote'];
                if ($vote) {
                    $validCases += 1;
                } else {
                    $judgingCases += 1;
                }
            }
        }
        $this->info("風紀委員: 今日投票{$sumCases}（{$validCases}票有效（非棄權），{$judgingCases}票还在进行中）");

        return true;
    }
    private function juryApi(): ApiJury
    {
        return $this->juryApi ??= new ApiJury($this->appContext()->request());
    }
}
