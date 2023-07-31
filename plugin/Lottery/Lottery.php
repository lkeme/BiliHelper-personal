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
use function Amp\delay;


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
//        include_once 'LotteryInfo.php';
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
        echo "ghahahahha";
        if (!getEnable('lottery')) return;
        if (TimeLock::getTimes() > time() && $this->last_task_finish) return;
        echo "ghahahahha";

        $this->lotteryTask();
        echo "ghahahahha";

        // 2-6小时 未完成6-10秒
        TimeLock::setTimes($this->last_task_finish ? (mt_rand(2, 6) * 60 * 60) : mt_rand(6, 10));
    }

    /**
     * 执行抽奖任务
     * @return void
     */
    protected function lotteryTask(): void
    {
//        $last_lottery_id = ($tmp = Cache::get('last_lottery_id')) ? $tmp : $this->calcLastLotteryId();
        $last_lottery_id = 3015989;
        $times = 0;
        // 参加抽奖
        while (true) {
//            if (LotteryInfo::isExist($last_lottery_id)) continue;
            if ($times > 9) {
                $this->last_task_finish = false;
                break;
            }
            $info = $this->getLotteryInfo($last_lottery_id);
            if ($info['status'] === -9999) { // 当前抽奖不存在
                $this->last_task_finish = true;
                break;
            } else if ($info['status'] === 2) { // 当前抽奖已开奖
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
//        $infos = LotteryInfo::getHasLotteryList();
//        foreach ($infos as $info) {
//            if ($times > 9) {
//                $this->last_task_finish = false;
//                break;
//            }
//            //TODO 删除动态
//            LotteryInfo::delete($info['lottery_id']);
//            $times++;
//        }
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
     * @param int $business_id
     * @return array
     */
    protected function getLotteryInfo(int $business_id): array
    {
        delay(3);
        $user = User::parseCookie();
        $url = 'https://api.vc.bilibili.com/lottery_svr/v1/lottery_svr/lottery_notice';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/'
        ];
        $payload = [
            'business_id' => $business_id,
            'business_type' => 10, // 1.转发动态 10.直播预约
            'csrf' => $user['csrf'],
        ];
        echo $business_id.PHP_EOL;
        $response = Request::getJson(true, 'pc', $url, $payload, $headers);
        print_r($response);

        // 抽奖不存在/请求错误/请求被拦截
        if ($response['code'] === -9999 || $response['code'] === 4000014 || $response['code'] === -412) {
            return [
                'status' => -9999,
            ];
        }
        $data = $response['data'];
        // 已开奖
        if ($data['lottery_time'] <= time()) {
            return [
                'status' => 2,
            ];
        }

        return [
            'business_id' => $business_id, // business_type=1时是动态Id business_type=10时是预约直播Id
            'lottery_id' => $data['lottery_id'], // 抽奖ID
            'lottery_time' => $data['lottery_time'], // 开奖时间
            'lottery_detail_url' => $data['lottery_detail_url'], // 抽奖详情页
            'need_feed' => $data['lottery_feed_limit'] === 1, // 是否需要关注
            'need_post' => $data['need_post'] === 1, //是否需要发货地址
            'sender_uid' => $data['sender_uid'], // 发起人UID
            'status' => $data['status'], // 0 未开奖 2 已开奖 -1 已失效 -9999 不存在
            'type' => $data['business_type'], // 1.转发动态 10.直播预约
            'ts' => $data['ts'], // 时间戳
            'prizes' => $this->parsePrizes($data), // 奖品
        ];
    }


    /**
     * 解析奖品
     * @param array $data
     * @return string
     */
    protected function parsePrizes(array $data): string
    {
        $prizes = '';
        $prizeData = [
            'first_prize' => ['一等奖', 'first_prize_cmt'],
            'second_prize' => ['二等奖', 'second_prize_cmt'],
            'third_prize' => ['三等奖', 'third_prize_cmt']
        ];
        //
        foreach ($prizeData as $key => $value) {
            if (isset($data[$key]) && isset($data[$value[1]])) {
                $prizes .= "{$value[0]}: {$data[$value[1]]} * {$data[$key]}\n";
            }
        }
        //
        return $prizes;
    }

    /**
     * 参加抽奖
     * @param array $info
     * @return void
     */
    protected function reserve(array $info): void
    {
        //
        $user = User::parseCookie();
        //
        $url = 'https://api.vc.bilibili.com/dynamic_mix/v1/dynamic_mix/reserve_attach_card_button';
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$info['sender_uid']}/dynamic"
        ];
        $payload = [
            'cur_btn_status' => 1,
            'dynamic_id' => $info['id_str'],
            'attach_card_type' => 'reserve',
            'reserve_id' => $info['business_id'],
            'reserve_total' => $info['reserve_total'],
            'spmid' => '',
            'csrf' => $user['csrf'],
        ];
        //
        $response = Request::postJson(true, 'pc', $url, $payload, $headers);
        //
        if ($response['code'] === 0 || $response['code'] === 7604003) { //预约成功/已经预约
            Log::info("抽奖: 预约直播成功 ID: {$info['business_id']} UP: {$info['sender_uid']} 预约人数: {$info['reserve_total']}");
            Log::info("抽奖: 地址: {$info['lottery_detail_url']}");
            Log::info("抽奖: 奖品: {$info['prizes']}");
        } else {
            Log::warning("抽奖: 预约直播失败 ReserveId: $info[business_id] Error: {$response['code']} -> {$response['message']}");
        }
    }


    /**
     * 提取动态参数
     * @param string $data
     * @return array
     */
    protected function extractDynamicParameters(string $data): array
    {
        $result = [];
        preg_match('/"reserve_total":(\d+)/', $data, $b); // 正则匹配
        if (isset($b[1])) {
            $result['reserve_total'] = $b[1];
        }
        preg_match('/"reserve_total": (\d+)/', $data, $b); // 正则匹配
        if (isset($b[1])) {
            $result['reserve_total'] = $b[1];
        }
        preg_match('/"id_str":(\d+)/', $data, $b); // 正则匹配
        if (isset($b[1])) {
            $result['id_str'] = $b[1];
        }
        preg_match('/"id_str": (\d+)/', $data, $b); // 正则匹配
        if (isset($b[1])) {
            $result['id_str'] = $b[1];
        }
        return $result;
    }

    /**
     * 过滤指定动态
     * @param array $dynamic_list
     * @param int $rid
     * @return array
     */
    protected function filterDynamic(array $dynamic_list, int $rid): array
    {
        foreach ($dynamic_list as $dynamic) {
            $dynamic_str = json_encode($dynamic);
            if (str_contains($dynamic_str, '"rid":' . $rid) || str_contains($dynamic_str, '"rid": ' . $rid)) {
                return $this->extractDynamicParameters($dynamic_str);
            }
        }
        return [];
    }


    /**
     * 获取指定空间动态列表
     * @param int $host_mid
     * @return array
     */
    protected function fetchSpaceDynamic(int $host_mid): array
    {
        $url = 'https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/space';
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$host_mid}/dynamic"
        ];
        $payload = [
            'offset' => '',
            'host_mid' => $host_mid,
            'timezone_offset' => '-480',
            'features' => 'itemOpusStyle'
        ];
        //
        $response = Request::getJson(true, 'pc', $url, $payload, $headers);
        //
        if ($response['code']) {
            Log::warning("获取({$host_mid})空间动态失败: {$response['code']} -> {$response['message']}");
            return [];
        }
        //
        return $response['data']['items'] ?? [];
    }

    protected function joinLottery(array $info): void
    {
        $dynamic_list = $this->fetchSpaceDynamic($info['sender_uid']);
        $dynamic = $this->filterDynamic($dynamic_list, $info['business_id']);
        if (!isset($dynamic['id_str']) || !isset($dynamic['reserve_total'])) {
            Log::warning("抽奖: 未找到指定动态 ReserveId: $info[business_id]");
            return;
        }
        // 合并数组
        $info = array_merge($info, $dynamic);
        $this->reserve($info);
    }

}
