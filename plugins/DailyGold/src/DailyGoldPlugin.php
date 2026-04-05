<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\DailyGold;

use Bhp\Api\XLive\AppRoom\V1\ApiDM;
use Bhp\Api\XLive\AppUcenter\V1\ApiUserTask;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Fake\Fake;

class DailyGoldPlugin extends BasePlugin implements PluginTaskInterface
{
    private AuthFailureClassifier $authFailureClassifier;
    private ?ApiDM $dmApi = null;
    private ?ApiUserTask $userTaskApi = null;

    /**
     * 插件信息
     *
     * @var array<string, int|string>
     */
    public ?array $info = [
        'hook' => 'DailyGold',
        'name' => 'DailyGold',
        'version' => '0.0.1',
        'desc' => '每日电池(APP)',
        'author' => 'Lkeme',
        'priority' => 1114,
        'cycle' => '24(小时)',
    ];

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('daily_gold')) {
            return TaskResult::keepSchedule();
        }

        $upUid = (int)$this->config('daily_gold.target_up_id', '');
        $upRoomId = (int)$this->config('daily_gold.target_room_id', '');
        if (!$upRoomId || !$upUid) {
            return TaskResult::keepSchedule();
        }

        $process = $this->getUserUnfinishedTask($upUid);

        return match ($process) {
            0 => $this->sendDM($upRoomId, Fake::emoji(true))
                ? TaskResult::after(mt_rand(30, 60) * 60)
                : TaskResult::after(mt_rand(30, 60) * 60),
            -1 => TaskResult::after(10 * 60),
            -2 => $this->userTaskReceiveRewards($upUid)
                ? TaskResult::nextAt(7, 0, 0, 1, 60)
                : TaskResult::after(10 * 60),
            -3 => TaskResult::nextAt(7, 0, 0, 1, 60),
            default => TaskResult::keepSchedule(),
        };
    }

    protected function getUserUnfinishedTask(int $upId): int
    {
        $response = $this->userTaskApi()->getUserTaskProgress($upId);
        $this->authFailureClassifier->assertNotAuthFailure($response, '每日电池: 获取任务进度时账号未登录');
        if ($response['code']) {
            $this->warning("每日电池: 获取任务进度失败 {$response['code']} -> {$response['message']}");

            return -1;
        }
        if ($response['data']['status'] == 3) {
            $this->info('每日电池: 账号已经领取奖励，故跳过');

            return -3;
        }
        if ($response['data']['is_surplus'] === -1) {
            $this->info('每日电池: 账号无法完成该任务，故跳过');

            return -3;
        }
        if (is_null($response['data']['task_list'])) {
            $this->info('每日电池: 没有可执行任务，故跳过');

            return -3;
        }

        $filteredArray = array_filter($response['data']['task_list'], function ($element) {
            return $element['status'] != 3 && str_contains($element['task_title'], '5条弹幕');
        });
        if (empty($filteredArray)) {
            $this->info('每日电池: 没有可执行任务，故跳过');

            return -3;
        }

        $filteredArray = array_filter($filteredArray, function ($element) {
            return $element['status'] == 2;
        });
        if ($filteredArray) {
            $this->info('每日电池: 任务已经完成，可以领取奖励');

            return -2;
        }

        return 0;
    }

    protected function sendDM(int $roomId, string $msg): bool
    {
        $response = $this->dmApi()->sendMsg($roomId, $msg);
        $this->authFailureClassifier->assertNotAuthFailure($response, '每日电池: 发送弹幕时账号未登录');
        if ($response['code']) {
            $this->warning("每日电池: 发送弹幕失败 {$response['code']} -> {$response['message']}");

            return false;
        }

        $this->info('每日电池: 发送弹幕成功');

        return true;
    }

    protected function userTaskReceiveRewards(int $upId): bool
    {
        $response = $this->userTaskApi()->userTaskReceiveRewards($upId);
        $this->authFailureClassifier->assertNotAuthFailure($response, '每日电池: 领取奖励时账号未登录');
        if ($response['code']) {
            $this->warning("每日电池: 领取任务奖励失败 {$response['code']} -> {$response['message']}");

            return false;
        }

        $this->notice("每日电池: 领取任务奖励成功 获得电池*{$response['data']['num']}");

        return true;
    }
    private function dmApi(): ApiDM
    {
        return $this->dmApi ??= new ApiDM($this->appContext()->request());
    }

    private function userTaskApi(): ApiUserTask
    {
        return $this->userTaskApi ??= new ApiUserTask($this->appContext()->request());
    }
}
