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

use Bhp\Cache\Cache;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;
use Bhp\Request\Request;
use Bhp\User\User;

class Lottery extends BasePlugin
{
    /**
     * 预计每日抽奖数量
     * 用于计算当前日期的MaxId
     */
    private const LOTTERY_PER_DAY = 75;
    /**
     * 抽奖活动开始日期
     * 用于计算当前日期的MaxId
     */
    private const START_LOTTERY_START_TIME = '2018-05-01';

    /**
     * 插件信息
     * @var array|string[]
     */
    protected ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'Lottery', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '抽奖', // 插件描述
        'author' => 'MoeHero',// 作者
        'priority' => 1113, // 插件优先级
        'cycle' => '2-6(小时)', // 运行周期
    ];
    /**
     * 上次任务是否完成
     * @var bool
     */
    protected bool $last_task_finish = false;

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        include_once 'LotteryInfo.php';
        TimeLock::initTimeLock();
        Cache::initCache();
        $plugin->register($this, 'execute');
    }

    /**
     * 执行
     * @return void
     */
    public function execute(): void
    {
        if (!getEnable('lottery')) return;
        if (TimeLock::getTimes() > time() && $this->last_task_finish) return;
        $this->lotteryTask();
        // 2-6小时 未完成6-10秒
        TimeLock::setTimes($this->last_task_finish ? (mt_rand(2, 6) * 60 * 60) : mt_rand(6, 10));
    }

    /**
     * 执行抽奖任务
     * @return void
     */
    protected function lotteryTask(): void
    {
        $last_lottery_id = ($tmp = Cache::get('last_lottery_id')) ? $tmp : $this->calcLastLotteryId();
        $times = 0;
        // 参加抽奖
        while (true) {
            if(LotteryInfo::isExist($last_lottery_id)) continue;
            if($times > 9) {
                $this->last_task_finish = false;
                break;
            }
            $info = $this->getLotteryInfo($last_lottery_id);
            if ($info['status'] === -9999) { // 当前抽奖不存在
                $this->last_task_finish = true;
                break;
            } else if($info['status'] === -1 || $info['status'] === 2) { // 当前抽奖无效/当前抽奖已开奖
                $last_lottery_id++;
                continue;
            }
            $this->joinLottery($info);
            $last_lottery_id++;
            Cache::set('last_lottery_id', $last_lottery_id);
            $times++;
        }
        $times = 0;
        // 删除动态
        $infos = LotteryInfo::getHasLotteryList();
        foreach ($infos as $info) {
            if($times > 9) {
                $this->last_task_finish = false;
                break;
            }
            //TODO 删除动态
            LotteryInfo::delete($info['lottery_id']);
            $times++;
        }
    }

    /**
     * 计算最新抽奖Id
     * @return int
     */
    protected function calcLastLotteryId(): int
    {
        $start_time = new DateTime(self::START_LOTTERY_START_TIME);
        $end_time = new DateTime();
        $elapsed_days = $start_time->diff($end_time)->days;
        $max_id = $elapsed_days * self::LOTTERY_PER_DAY;
        $min_id = max($max_id - 10000, 0);

        // 如果计算出的MaxId不是未使用的Id，则每次加5000
        while (true) {
            $info = $this->getLotteryInfo($max_id);
            if ($info['status'] === -9999) break;
            $min_id = $max_id;
            $max_id += 5000;
        }
        // 如果计算出的MinId是未使用的Id，则每次减5000
        while (true) {
            $info = $this->getLotteryInfo($min_id);
            if ($info['status'] !== -9999) break;
            $max_id = $min_id;
            $min_id -= 5000;
        }

        $times = 0;
        while (true) {
            $times++;
            $middle = intval(($min_id + $max_id) / 2);
            $info = $this->getLotteryInfo($middle);
            if ($info['status'] !== -9999) {
                $min_id = $middle;
            } else {
                $max_id = $middle;
            }
            if ($max_id - $min_id == 1) break;
        }
        Log::info("抽奖：计算出最新抽奖Id Id: $max_id 请求次数: $times");

        // 抽奖模式 0.从最新Id开始抽奖 1.从最新Id的前2400个开始抽奖
        if (getConf('lottery.lottery_mode', 0) == 1) return $max_id - 2400;
        return $max_id;
    }

    /**
     * 获取抽奖信息
     * @param int $lottery_id
     * @return array
     */
    protected function getLotteryInfo(int $lottery_id): array
    {
        $user = User::parseCookie();
        $url = 'https://api.vc.bilibili.com/lottery_svr/v1/lottery_svr/detail_by_lid';
        $payload = [
            'lottery_id' => $lottery_id,
            'csrf' => $user['csrf'],
        ];
        $response = Request::getJson(true, 'pc', $url, $payload);

        // 抽奖不存在
        if ($response['code'] === -9999) {
            return [
                'status' => -9999,
            ];
        }
        $data = $response['data'];
        // business_type为0的则为无效抽奖
        if ($data['business_type'] === 0) {
            return [
                'status' => -1,
            ];
        }
        // 已开奖
        if ($data['lottery_time'] <= time())  {
            return [
                'status' => 2,
            ];
        }
        return [
            'lottery_id' => $lottery_id,
            'status' => $data['status'], // 0 未开奖 2 已开奖 -1 已失效 -9999 不存在
            'type' => $data['business_type'], // 1.转发动态 10.直播预约
            'need_feed' => $data['lottery_feed_limit'] === 1, // 是否需要关注
            'business_id' => $data['business_id'], // business_type=1时是动态Id business_type=10时是预约直播Id
        ];
    }

    /**
     * 参加抽奖
     * @param array $info
     * @return void
     */
    protected function joinLottery(array $info): void
    {
        $dynamic_enable = getConf('lottery.dynamic_enable', false);
        $live_enable = getConf('lottery.live_enable', false);

        if ($info['type'] === 1 && $dynamic_enable) {
            $dynamic_id = $info['business_id'];
            //TODO 转发动态
            //TODO 关注用户并放到指定分组
        } else if ($info['type'] === 10 && $live_enable) {
            $reserve_id = $info['business_id'];
            $this->reserveLive($reserve_id);
        }
    }

    /**
     * 删除动态
     * @param string $dynamic_id
     * @return void
     */
    protected function deleteDynamic(string $dynamic_id): void
    {
        //TODO 删除动态
    }

    /**
     * 预约直播
     * @param int $reserve_id
     * @return void
     */
    protected function reserveLive(int $reserve_id): void
    {
        $user = User::parseCookie();
        $url = 'https://api.vc.bilibili.com/dynamic_mix/v1/dynamic_mix/reserve_attach_card_button';
        $payload = [
            'cur_btn_status' => 1,
            'reserve_id' => $reserve_id,
            'csrf' => $user['csrf'],
        ];
        $response = Request::postJson(true, 'pc', $url, $payload);

        if($response['code'] === 0 || $response['code'] === 7604003) { //预约成功/已经预约
            Log::info("抽奖: 预约直播成功 ReserveId: $reserve_id");
        } else {
            Log::warning("抽奖: 预约直播失败 ReserveId: $reserve_id Error: {$response['code']} -> {$response['message']}");
        }
    }
}
