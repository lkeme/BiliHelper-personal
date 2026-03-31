<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

use Bhp\Api\XLive\AppRoom\V1\ApiDM;
use Bhp\Api\XLive\AppUcenter\V1\ApiUserTask;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Fake\Fake;

class DailyGold extends BasePlugin implements PluginTaskInterface
{
    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'DailyGold', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '每日电池(APP)', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1114, // 插件优先级
        'cycle' => '24(小时)', // 运行周期
    ];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('daily_gold')) {
            return TaskResult::keepSchedule();
        }

        $up_uid = (int)$this->config('daily_gold.target_up_id', '');
        $up_room_id = (int)$this->config('daily_gold.target_room_id', '');
        if (!$up_room_id || !$up_uid) {
            return TaskResult::keepSchedule();
        }

        $process = $this->getUserUnfinishedTask($up_uid);

        switch ($process) {
            case 0:
                $this->sendDM($up_room_id, Fake::emoji(true));
                return TaskResult::after(mt_rand(30, 60) * 60);
            case -1:
                return TaskResult::after(10 * 60);
            case -2:
                if (!$this->userTaskReceiveRewards($up_uid)) {
                    return TaskResult::after(10 * 60);
                } else {
                    return TaskResult::nextAt(7, 0, 0, 1, 60);
                }
            case -3:
                return TaskResult::nextAt(7, 0, 0, 1, 60);
            default:
                return TaskResult::keepSchedule();
        }
    }

    /**
     * 获取任务进度
     * @param int $up_id
     * @return int
     */
    protected function getUserUnfinishedTask(int $up_id): int
    {
        $response = ApiUserTask::getUserTaskProgress($up_id);
        //
        if ($response['code']) {
            Log::warning("每日电池: 获取任务进度失败 {$response['code']} -> {$response['message']}");
            return -1;
        }
        // 领取完成
        if ($response['data']['status'] == 3) {
            Log::info("每日电池: 账号已经领取奖励，故跳过");
            return -3;
        }
        //
        if ($response['data']['is_surplus'] === -1) {
            Log::info("每日电池: 账号无法完成该任务，故跳过");
            return -3;
        }
        //
        if (is_null($response['data']['task_list'])) {
            Log::info("每日电池: 没有可执行任务，故跳过");
            return -3;
        }
        //
        $filteredArray = array_filter($response['data']['task_list'], function ($element) {
            return ($element['status'] != 3 && str_contains($element['task_title'], '5条弹幕'));
        });
        if (empty($filteredArray)) {
            Log::info("每日电池: 没有可执行任务，故跳过");
            return -3;
        }
        //
        $filteredArray = array_filter($filteredArray, function ($element) {
            return ($element['status'] == 2);
        });
        if ($filteredArray) {
            Log::info("每日电池: 任务已经完成，可以领取奖励");
            return -2;
        }
        //
        return 0;
    }


    /**
     * 发送弹幕(外部调用APP)
     * @param int $room_id
     * @param string $msg
     * @return bool
     */
    protected function sendDM(int $room_id, string $msg): bool
    {
        $response = ApiDM::sendMsg($room_id, $msg);
        if ($response['code']) {
            Log::warning("每日电池: 发送弹幕失败 {$response['code']} -> {$response['message']}");
            return false;
        }
        //
        Log::info('每日电池: 发送弹幕成功');
        return true;
    }

    /**
     * 领取任务奖励
     * @param int $up_id
     * @return bool
     */
    protected function userTaskReceiveRewards(int $up_id): bool
    {
        $response = ApiUserTask::userTaskReceiveRewards($up_id);
        if ($response['code']) {
            Log::warning("每日电池: 领取任务奖励失败 {$response['code']} -> {$response['message']}");
            return false;
        }
        //
        Log::notice("每日电池: 领取任务奖励成功 获得电池*{$response['data']['num']}");
        return true;

    }

}


