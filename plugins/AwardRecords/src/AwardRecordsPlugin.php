<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\AwardRecords;

use Bhp\Api\Lottery\V1\ApiAward;
use Bhp\Api\XLive\GeneralInterface\V1\ApiGuardBenefit;
use Bhp\Api\XLive\LotteryInterface\V1\ApiAnchor;
use Bhp\Api\XLive\Revenue\V1\ApiWallet;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;

class AwardRecordsPlugin extends BasePlugin implements PluginTaskInterface
{
    private const CACHE_SCOPE = 'AwardRecords';

    private AuthFailureClassifier $authFailureClassifier;
    private ?ApiWallet $operationWalletApi = null;
    private ?ApiAward $awardApi = null;
    private ?ApiAnchor $anchorApi = null;
    private ?ApiGuardBenefit $guardBenefitApi = null;

    /**
     * @var array<string, list<string>>
     */
    protected array $records = [];

    /**
     * @var array<string, int>
     */
    protected array $locks = [
        'operation' => 0,
        'award' => 0,
        'celestial' => 0,
        'bonus' => 0,
    ];

    /**
     * 插件信息
     *
     * @var array<string, int|string>
     */

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('award_records')) {
            return TaskResult::keepSchedule();
        }

        $this->awardRecordsTask();

        return TaskResult::after(5 * 60);
    }

    protected function awardRecordsTask(): void
    {
        $this->records = ($tmp = $this->cacheGet('records', self::CACHE_SCOPE, null)) ? $tmp : $this->initRecords();

        if ($this->locks['operation'] < time()) {
            $this->operation();
        }
        if ($this->locks['award'] < time()) {
            $this->award();
        }
        if ($this->locks['celestial'] < time()) {
            $this->celestial();
        }
        if ($this->locks['bonus'] < time()) {
            $this->bonus();
        }

        $this->cacheSet('records', $this->records, self::CACHE_SCOPE);
    }

    protected function operation(string $title = '运营奖惩'): bool
    {
        $response = $this->operationWalletApi()->apCenterList();
        $this->authFailureClassifier->assertNotAuthFailure($response, "获奖记录: 获取{$title}时账号未登录");

        if ($response['code']) {
            $this->warning("获奖记录: 获取{$title}失败 {$response['code']} -> {$response['message']}");
            $this->locks['operation'] = time() + 6 * 60 * 60;

            return false;
        }

        $now = date('m') . '月' . date('d') . '日';
        foreach ($response['data']['list'] as $data) {
            $info = $data['md'] . '-' . $data['desc'];

            if (!in_array($info, $this->records['operation'], true)) {
                $this->records['operation'][] = $info;
            }

            if ($now !== $data['md']) {
                continue;
            }

            $this->notice($info);
            $this->notify($title, $info);
        }
        $this->locks['operation'] = time() + 24 * 60 * 60;

        return true;
    }

    protected function award(string $title = '获奖记录'): bool
    {
        $response = $this->awardApi()->awardList();
        $this->authFailureClassifier->assertNotAuthFailure($response, "获奖记录: 获取{$title}时账号未登录");

        if ($response['code']) {
            $this->warning("获奖记录: 获取{$title}失败 {$response['code']} -> {$response['message']}");
            $this->locks['award'] = time() + 60 * 60;

            return false;
        }

        foreach ($response['data']['list'] as $data) {
            $info = $data['create_time'] . '-' . $data['id'] . '-' . $data['source'] . '-' . $data['gift_name'];

            if (!in_array($info, $this->records['award'], true)) {
                $this->records['award'][] = $info;
            }

            $createTime = strtotime($data['create_time']);
            $day = (int)ceil((time() - $createTime) / 86400);
            if ($day <= 2 && $data['update_time'] === '') {
                $this->notice($info);
                $this->notify($title, $info);
            }
        }
        $this->locks['award'] = time() + 6 * 60 * 60;

        return true;
    }

    protected function celestial(string $title = '天选时刻'): bool
    {
        $response = $this->anchorApi()->awardRecord();
        $this->authFailureClassifier->assertNotAuthFailure($response, "获奖记录: 获取{$title}时账号未登录");

        if ($response['code']) {
            $this->warning("获奖记录: 获取{$title}失败 {$response['code']} -> {$response['message']}");
            $this->locks['celestial'] = time() + 30 * 60;

            return false;
        }

        foreach ($response['data']['list'] as $data) {
            $info = $data['end_time'] . '-' . $data['id'] . '-' . $data['anchor_name'] . '-' . $data['award_name'];

            if (!in_array($info, $this->records['celestial'], true)) {
                $this->records['celestial'][] = $info;
            }

            $endTime = strtotime($data['end_time']);
            $day = (int)ceil((time() - $endTime) / 86400);
            if ($day <= 2) {
                $this->notice($info);
                $this->notify($title, $info);
            }
        }
        $this->locks['celestial'] = time() + 10 * 60;

        return true;
    }

    protected function bonus(string $title = '航海回馈'): bool
    {
        $response = $this->guardBenefitApi()->winListByUser();
        $this->authFailureClassifier->assertNotAuthFailure($response, "获奖记录: 获取{$title}时账号未登录");

        if ($response['code']) {
            $this->warning("获奖记录: 获取{$title}失败 {$response['code']} -> {$response['message']}");
            $this->locks['bonus'] = time() + 6 * 30 * 60;

            return false;
        }

        foreach ($response['data']['list'] as $data) {
            $info = $data['settlement_time'] . '-' . $data['win_id'] . '-' . $data['anchor_name'] . '-' . $data['award_name'];

            if (!in_array($info, $this->records['bonus'], true)) {
                $this->records['bonus'][] = $info;
            }

            $settlementTime = strtotime($data['settlement_time']);
            if (time() < $settlementTime) {
                $this->notice($info);
                $this->notify($title, $info);
            }
        }
        $this->locks['bonus'] = time() + 24 * 60 * 60;

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function initRecords(): array
    {
        return [
            'operation' => [],
            'award' => [],
            'celestial' => [],
            'bonus' => [],
        ];
    }

    private function operationWalletApi(): ApiWallet
    {
        return $this->operationWalletApi ??= new ApiWallet($this->appContext()->request());
    }

    private function awardApi(): ApiAward
    {
        return $this->awardApi ??= new ApiAward($this->appContext()->request());
    }

    private function anchorApi(): ApiAnchor
    {
        return $this->anchorApi ??= new ApiAnchor($this->appContext()->request());
    }

    private function guardBenefitApi(): ApiGuardBenefit
    {
        return $this->guardBenefitApi ??= new ApiGuardBenefit($this->appContext()->request());
    }
}
