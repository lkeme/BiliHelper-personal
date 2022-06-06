<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

use Bhp\Api\Space\ApiReservation;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;

class LiveReservation extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    protected ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'LiveReservation', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '预约直播有奖', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1109, // 插件优先级
        'cycle' => '1-3(小时)', // 运行周期
    ];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        //
        TimeLock::initTimeLock();
        // $this::class
        $plugin->register($this, 'execute');
    }

    /**
     * @use 执行
     * @return void
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('live_reservation')) return;
        //
        $this->reservationTask();
        // 1-3小时
        TimeLock::setTimes(mt_rand(1, 3) * 60 * 60);
    }

    /**
     * @return void
     */
    protected function reservationTask(): void
    {
        $vmids = getConf('live_reservation.vmids');
        $vmids = explode(',', $vmids);
        // 获取目标列表->获取预约列表->执行预约列表
        foreach ($vmids as $vmid) {
            $reservation_list = $this->fetchReservation($vmid);
            foreach ($reservation_list as $reservation) {
                $this->reserve($reservation);
            }
        }
    }

    /**
     * @use 获取预约列表
     * @param string $vmid
     * @return array
     */
    protected function fetchReservation(string $vmid): array
    {
        //
        $reservation_list = [];
        // {"code":0,"message":"0","ttl":1,"data":[{"sid":253672,"name":"直播预约：创世之音-虚拟偶像演唱会","total":6382,"stime":1636716437,"etime":1637408100,"is_follow":1,"state":100,"oid":"","type":2,"up_mid":9617619,"reserve_record_ctime":1636731801,"live_plan_start_time":1637406000,"lottery_type":1,"lottery_prize_info":{"text":"预约有奖：小电视年糕抱枕、哔哩哔哩小电视樱花毛绒抱枕大号、哔哩哔哩小夜灯","lottery_icon":"https://i0.hdslb.com/bfs/activity-plat/static/ce06d65bc0a8d8aa2a463747ce2a4752/rgHplMQyiX.png","jump_url":"https://www.bilibili.com/h5/lottery/result?business_id=253672\u0026business_type=10\u0026lottery_id=76240"},"show_total":true,"subtitle":""},{"sid":246469,"name":"直播预约：创世之音-YuNi个人演唱会","total":3555,"stime":1636367836,"etime":1637494500,"is_follow":0,"state":100,"oid":"","type":2,"up_mid":9617619,"reserve_record_ctime":0,"live_plan_start_time":1637492400,"show_total":true,"subtitle":""}]}
        $response = ApiReservation::reservation($vmid);
        //
        if ($response['code']) {
            Log::warning("预约直播: 获取预约列表失败: {$response['code']} -> {$response['message']}");
        } else {
            // data == NULL
            $de_data = $response['data'] ?: [];
            foreach ($de_data as $data) {
                $result = self::checkLottery($data);
                if (!$result) continue;
                $reservation_list[] = $result;
            }
            //
            Log::info('预约直播: 获取预约列表成功 ' . count($reservation_list));
        }
        //
        return $reservation_list;
    }

    /**
     * @use 检测有效抽奖
     * @param array $data
     * @return bool|array
     */
    protected function checkLottery(array $data): bool|array
    {
        // 已经过了有效时间
        if ($data['etime'] <= time()) {
            return false;
        }
        // 已经预约过了
        if ($data['is_follow']) {
            return false;
        }
        // 有预约抽奖
        if (array_key_exists('lottery_prize_info', $data) && array_key_exists('lottery_type', $data)) {
            return [
                'sid' => $data['sid'], // 246469
                'name' => $data['name'], // "直播预约：创世之音-虚拟偶像演唱会"
                'vmid' => $data['up_mid'], // 9617619
                'jump_url' => $data['lottery_prize_info']['jump_url'], // "https://www.bilibili.com/h5/lottery/result?business_id=253672&business_type=10&lottery_id=76240"
                'text' => $data['lottery_prize_info']['text'], // "预约有奖：小电视年糕抱枕、哔哩哔哩小电视樱花毛绒抱枕大号、哔哩哔哩小夜灯"
            ];
        }
        return false;
    }

    /**
     * @use 尝试预约并抽奖
     * @param array $data
     */
    protected function reserve(array $data): void
    {
        // {"code":0,"message":"0","ttl":1}
        $response = ApiReservation::reserve($data['sid'], $data['vmid']);
        //
        Log::info("预约直播: {$data['name'] }|{$data['vmid']}|{$data['sid']}");
        Log::info("预约直播: {$data['text']}");
        Log::info("预约直播: {$data['jump_url']}");
        //
        if ($response['code']) {
            Log::warning("预约直播: 尝试预约并抽奖失败 {$response['code']} -> {$response['message']}");
        } else {
            Log::notice("预约直播: 尝试预约并抽奖成功 {$response['message']}");
        }
    }


}
 