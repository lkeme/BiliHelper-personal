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
        // {"task_id":45,"type":2,"goal_type":2,"target":0,"task_title":"看新主播30秒,并发弹幕鼓励TA","title_param":["30","1"],"task_sub_title":"点击「去看看」做任务","sub_title_param":null,"total_reward":20,"received_reward":0,"reward_type":1,"rules":"1.必须点击“去看看”按钮，进入的新主播直播间才计为任务直播间，在这些直播间内才可完成本任务；\n2.每个任务直播间，每天仅可完成1次任务，或领取1次奖励，请上下滑浏览更多任务直播间，才能完成更多任务；\n3.观看时长要求：30秒需单次在同一个任务直播间内连续观看，退房后或上下滑后，观看时长将重新计算，建议观看时多和主播互动哦；\n4.发送弹幕要求：建议发送友好的，跟当前直播内容相关的弹幕，如鼓励夸赞主播、鼓励主播表演才艺等，不能是纯数字、纯字母、纯符号等无意义内容，也不能是表情包，且字数必须大于等于3，内容符合哔哩哔哩社区公约，否则可能不计为有效弹幕；\n5.由于参与人数较多，任务直播间可能会失效，若在任务直播间打开福利中心，本任务按钮显示“去看看”，则说明本任务直播间已失效，请上下滑浏览更多任务直播间。","priority":0,"progress":0,"status":1,"schema_dst":0,"btn_text":"暂无新任务","finished_text":"观看任务已达成","finished_sub_text":"","num":1,"available":0}"
        $process = $this->getUserUnfinishedTask($up_uid);

        switch ($process) {
            case 0:
                // 默认一次弹幕进度
                $this->sendDM($up_room_id, Fake::emoji(true));
                TimeLock::setTimes(mt_rand(30, 60) * 60);
                break;
            case -1:
                // 获取失败
                TimeLock::setTimes(10 * 60);
                break;
            case -2:
                // 领取ing
                if (!$this->userTaskReceiveRewards($up_uid)) {
                    // 领取失败 TODO
                    // [code] => 27000002
                    // [message] => 领取失败，请重试
                    // [data][num] => 0
                    TimeLock::setTimes(10 * 60);
                } else {
                    // TODO 因活动变动，每个人的任务详情不一致，暂时解决方案，可能会影响电池的获取
                    // 领取完成
                    TimeLock::setTimes(TimeLock::timing(7, 0, 0, true));
                }
                break;
            case -3:
                // 领取完成
                TimeLock::setTimes(TimeLock::timing(7, 0, 0, true));
                break;
            default:
                break;
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
        if ($filteredArray){
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


