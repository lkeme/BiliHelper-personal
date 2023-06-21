<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
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
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;
use Bhp\Util\Fake\Fake;

class DailyGold extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    protected ?array $info = [
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
        // 时间锁
        TimeLock::initTimeLock();
        // $this::class
        $plugin->register($this, 'execute');
    }

    /**
     * 执行
     * @return void
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('daily_gold')) return;
        //
        $up_uid = (int)getConf('daily_gold.target_up_id', '');
        $up_room_id = (int)getConf('daily_gold.target_room_id', '');
        if (!$up_room_id || !$up_uid) return;
        //
        $process = $this->getUserTaskProgress();
        switch ($process) {
            case -3:
                // 领取完成
                TimeLock::setTimes(TimeLock::timing(7, 0, 0, true));
                break;
            case -1:
                // 获取失败
                TimeLock::setTimes(10 * 60);
                break;
            case 0:
                // 领取ing
                if (!$this->userTaskReceiveRewards($up_uid)) {
                    // 领取失败 TODO
                    // [code] => 27000002
                    // [message] => 领取失败，请重试
                    // [data][num] => 0
                    TimeLock::setTimes(10 * 60);
                }else{
                    // TODO 因活动变动，每个人的任务详情不一致，暂时解决方案，可能会影响电池的获取
                    // 领取完成
                    TimeLock::setTimes(TimeLock::timing(7, 0, 0, true));
                }
                break;
            default:
                // 默认一次弹幕进度
                $this->sendDM($up_room_id, Fake::emoji(true));
                TimeLock::setTimes(mt_rand(30, 60) * 60);
                break;
        }
    }

    /**
     * 获取任务进度
     * @return int
     */
    protected function getUserTaskProgress(): int
    {
        $response = ApiUserTask::getUserTaskProgress();
        if ($response['code']) {
            Log::warning("每日电池: 获取任务进度失败 {$response['code']} -> {$response['message']}");
            return -1;
        }
        //
        $target = (int)$response['data']['target'];
        $progress = (int)$response['data']['progress'];
        Log::info("每日电池: 当前任务进度 $progress/$target");
        // 领取完成
        if ($response['data']['status'] == 3) {
            return -3;
        }
        // 可以领取
//        if ($response['data']['status'] == 2) {
//            return 0;
//        }
        return (int)($response['data']['target'] - $response['data']['progress']);
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


