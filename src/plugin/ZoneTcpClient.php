<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Util\TimeLock;

use Amp\Delayed;
use Exception;
use Socket\Raw\Factory;

class ZoneTcpClient
{
    use TimeLock;
    private static $raffle_id = 0;
    private static $raffle_list = [];
    private static $server_addr = null;
    private static $server_key = null;

    private static $area_id;
    private static $room_id;
    private static $client;
    private static $client_maps = [];
    private static $trigger_restart = [];
    private static $socket_timeout = 0;


    /**
     * @use 入口
     */
    public static function run()
    {
        if (self::getLock() > time() || getenv('USE_ZONE_SERVER') == 'false') {
            return;
        }
        self::init();
        self::updateConnection();
        self::heartBeat();
        self::receive();
        self::pushHandle();
    }


    /**
     * @use 初始化
     */
    private static function init()
    {
        if (empty(getenv('ZONE_SERVER_ADDR'))) {
            exit('推送服务器信息不完整, 请检查配置文件!');
        }
        if (!self::$server_addr) {
            self::$server_addr = getenv('ZONE_SERVER_ADDR');
        }
        if (!self::$client) {
            self::initConnect();
        }
    }

    /**
     * @use 初始化连接
     */
    private static function initConnect()
    {
        $areas = Live::fetchLiveAreas();
        foreach ($areas as $area_id) {
            self::$client_maps["server{$area_id}"] = ["area_id" => null, "room_id" => null, "client" => null, "heart_beat" => 0];
            self::triggerReConnect([
                'area_id' => $area_id,
                'wait_time' => time()
            ]);
        }
    }


    /**
     * @use 触发重连
     * @param array $area_data
     */
    private static function triggerReConnect(array $area_data)
    {
        array_push(self::$trigger_restart, $area_data);
    }

    /**
     * @use 更新连接
     */
    private static function updateConnection()
    {
        $num = count(self::$trigger_restart);
        for ($i = 0; $i < $num; $i++) {
            $area_data = array_shift(self::$trigger_restart);
            if (is_null($area_data)) {
                break;
            }
            if (time() < $area_data['wait_time']) {
                array_push(self::$trigger_restart, $area_data);
                continue;
            }
            Log::notice("update_connections triggered, info: {$area_data['area_id']}");
            $area_info = Live::areaToRid($area_data['area_id']);
//            $area_info = [
//                'area_id' => $area_id,
//                'room_id' => 23058
//            ];
            self::update($area_info);
        }
    }

    /**
     * @use 更新操作
     * @param array $area_info
     */
    private static function update(array $area_info)
    {
        self::$area_id = $area_info['area_id'];
        self::$room_id = $area_info['room_id'];
        try {
            self::$client = (new Factory())->createClient(self::$server_addr, 40);
            self::$client->setBlocking(false);
            self::sendHandShake();
            self::$client_maps["server" . self::$area_id]['client'] = self::$client;
            self::$client_maps["server" . self::$area_id]['area_id'] = self::$area_id;
            self::$client_maps["server" . self::$area_id]['room_id'] = self::$room_id;
            self::$client_maps["server" . self::$area_id]['heart_beat'] = time() + 20;
            Log::info("连接到 " . self::$client->getPeerName() . "#" . self::$area_id . " 推送服务器");
        } catch (Exception $e) {
            Log::error("连接到 #" . self::$area_id . " 推送服务器失败, {$e->getMessage()}");
            self::triggerReConnect([
                'area_id' => self::$area_id,
                'wait_time' => time() + 60
            ]);
        }
    }

    /**
     * 判断字符串是否为 Json 格式
     * @param string $data Json 字符串
     * @param bool $assoc 是否返回对象or关联数组，默认返回关联数组
     * @return array|bool|object 成功返回转换后的对象或数组，失败返回 false
     */
    private static function analyJson($data = '', $assoc = true)
    {
        if (is_array($data)) {
            return $data;
        }
        $data = json_decode($data, $assoc);
        if (($data && is_object($data)) || (is_array($data) && !empty($data))) {
            return $data;
        }
        return false;
    }


    /**
     * @use 响应数据
     * @param $msg
     * @param $type
     * @return bool
     */
    private static function onMessage($msg, $type)
    {
        // 心跳后回复人气
        if ($type == 3) {
            // $num = unpack('N', $msg)[1];
            // Log::info("当前直播间现有 {$num} 人聚众搞基!");
            return false;
        }
        $de_raw = self::analyJson($msg, true);
        // 进入房间返回
        if (isset($de_raw['code']) && !$de_raw['code']) {
            return false;
        }
        // 部分cmd抽风
        if (!$de_raw || !isset($de_raw['cmd'])) {
            Log::warning("解析错误: {$msg}");
            return false;
        }
        $data = [];
        switch ($de_raw['cmd']) {
            case 'TV_START':
                // 小电视
                break;
            case 'RAFFLE_START':
                // 活动礼物
                break;
            case 'LOTTERY_START':
                // 抽奖
                break;
            case 'PK_LOTTERY_START':
                // 乱斗
                break;
            case 'GUARD_LOTTERY_START':
                // 舰长
                break;
            case 'ALL_MSG':
                // 未知
                break;
            case 'NOTICE_MSG':
                $msg_type = $de_raw['msg_type'];
                $msg_self = $de_raw['msg_self'];
                $msg_common = str_replace(' ', '', $de_raw['msg_common']);
                $real_room_id = $de_raw['real_roomid'];
                if (in_array($msg_type, [2, 8])) {
                    $data = [
                        'room_id' => $real_room_id,
                        'raffle_id' => self::$raffle_id++,
                        'raffle_title' => $msg_self,
                        'raffle_type' => 'raffle',
                        'source' => $msg
                    ];
                    // echo self::$room_id . '--' . $real_room_id . PHP_EOL;
                }
                if ($msg_type == 6 && strpos($msg_common, '节奏风暴') !== false) {
                    $data = [
                        'room_id' => $real_room_id,
                        'raffle_id' => self::$raffle_id++,
                        'raffle_title' => '节奏风暴',
                        'raffle_type' => 'raffle',
                        'source' => $msg
                    ];
                }
                break;
            case 'GUARD_MSG':
                $data = [
                    'room_id' => $de_raw['roomid'],
                    'raffle_id' => self::$raffle_id++,
                    'raffle_title' => '总督舰长',
                    'raffle_type' => 'raffle',
                    'source' => $msg
                ];
                break;
            case 'SPECIAL_GIFT':
                if (array_key_exists('39', $de_raw['data'])) {
                    if ($de_raw['data']['39']['action'] == 'start') {
                        $data = [
                            'room_id' => $de_raw['roomid'],
                            'raffle_id' => self::$raffle_id++,
                            'raffle_title' => '节奏风暴',
                            'raffle_type' => 'raffle',
                            'source' => $msg
                        ];
                    }
                }
                break;
            case 'SYS_GIFT':
                /**
                 * 系统礼物消息, 广播
                 */
                break;
            case 'SYS_MSG':
                /**
                 * 系统消息, 广播
                 */
                break;
            // TODO 支持更多消息类型
            default:
                $data = [];
                break;
        }

        if (!empty($data)) {
            unset($data['source']);
            if (!isset(self::$raffle_list[$data['raffle_type']])) {
                self::$raffle_list[$data['raffle_type']] = [];
            }
            array_push(self::$raffle_list[$data['raffle_type']], $data);
            // Log::info("获取到分区 #" . self::$area_id . "# {$data['source']}");
            // print_r($data);
        }
    }


    /**
     * @推送到上游处理
     */
    private static function pushHandle()
    {
        foreach (self::$raffle_list as $type => $data) {
            $temp_room_id = 0;
            foreach (self::$raffle_list[$type] as $raffle) {
                if ($temp_room_id != $raffle['room_id']) {
                    DataTreating::distribute($raffle);
                    $temp_room_id = $raffle['room_id'];
                }
            }
        }
        self::$raffle_list = [];
    }

    /**
     * @use 响应关闭
     * @param $client
     */
    private static function onClosed($client)
    {
    }


    /**
     * @use 发送握手包
     * @return bool
     */
    private static function sendHandShake()
    {
        return self::writer(self::genHandshakePkg(self::$room_id));
    }

    /**
     * @use 心跳包
     * @return string
     */
    private static function genHeartBeatPkg(): string
    {
        return self::packMsg('', 0x0002);
    }


    /**
     * @use 握手包
     * @param $room_id
     * @return string
     */
    private static function genHandshakePkg($room_id): string
    {
        return self::packMsg(json_encode([
            "uid" => 0,
            "roomid" => intval($room_id),
            "protover" => 2,
            "platform" => "web",
            "clientver" => "1.10.6",
            "type" => 2,
            "key" => Live::getDanMuToken($room_id)
        ]), 0x0007);
    }

    /**
     * @use 打包数据
     * @param $value
     * @param $option
     * @return string
     */
    private static function packMsg($value, $option)
    {
        $head = pack('NnnNN', 0x10 + strlen($value), 0x10, 0x01, $option, 0x0001);
        return $head . $value;
    }


    /**
     * @use 解包数据
     * @param $value
     * @return int|mixed
     */
    private static function unPackMsg($value)
    {
        if (strlen($value) < 4) exit();
        $head = unpack('Npacklen/nheadlen/nver/Nop/Nseq', $value);
        // Log::info(json_encode($head, true));
        return $head;
    }


    /**
     * @use 心跳
     */
    private static function heartBeat()
    {
        foreach (self::$client_maps as $key => $client_info) {
            if ($client_info['heart_beat'] > time()) {
                continue;
            }
            self::$client = $client_info['client'];
            self::$area_id = $client_info['area_id'];
            self::$room_id = $client_info['room_id'];
            self::writer(self::genHeartBeatPkg());
            self::$client_maps[$key]['heart_beat'] = time() + 20;
        }
    }

    /**
     * @use 读数据
     * @param $length
     * @param $is_header
     * @return array|bool|false
     */
    private static function reader($length, $is_header = false)
    {
        $data = false;
        try {
            if (self::$client->selectRead(self::$socket_timeout)) {
                $ret = 0;
                $socket = self::$client->getResource();
                while ($length) {
                    $cnt = 0;
                    $r = array($socket);
                    $w = NULL;
                    $e = NULL;
                    while ($cnt++ < 60) {
                        $ret = socket_select($r, $w, $e, 1);
                        if ($ret === false)
                            throw new Exception("Connection failure");
                        if ($ret)
                            break;
                    }
                    $ret = socket_recv($socket, $buffer, $length, 0);
                    if ($ret < 1) {
                        print_r("Socket error: [{$ret}] [{$length}]" . PHP_EOL);
                        throw new Exception("Connection failure");
                    }
                    $data .= $buffer;
                    unset($buffer);
                    $length -= $ret;
                }
                if ($is_header) $data = self::unPackMsg($data);
            }
        } catch (Exception $exception) {
            self::triggerReConnect([
                'area_id' => self::$area_id,
                'wait_time' => time() + 60
            ]);
            $data = false;
        }
        return $data;
    }

    /**
     * @use 写数据
     * @param $data
     * @return bool
     */
    private static function writer($data)
    {
        $status = false;
        try {
            while (self::$client->selectWrite(self::$socket_timeout)) {
                $status = self::$client->write($data);
                break;
            }
        } catch (Exception $exception) {
            self::triggerReConnect([
                'area_id' => self::$area_id,
                'wait_time' => time() + 60
            ]);
        }
        return $status;
    }


    /**
     * @use 读取数据
     */
    private static function receive()
    {
        foreach (self::$client_maps as $client_info) {
            self::$client = $client_info['client'];
            self::$area_id = $client_info['area_id'];
            self::$room_id = $client_info['room_id'];
            $head = self::reader(16, true);
            if (!$head) {
                // 长度为0 ，空信息
                continue;
            }
            $length = isset($head['packlen']) ? $head['packlen'] : 16;
            $type = isset($head['op']) ? $head['op'] : 0x0000;
            $len_body = $length - 16;
            Log::debug("(len=$len_body)");
            if (!$len_body)
                continue;
            $body = self::reader($len_body);
            if ($body) {
                if ($head['ver'] == 2) {
                    $data_list = self::v2_split($body, $len_body);
                    foreach ($data_list as $body) {
                        self::onMessage($body, $type);
                    }
                } else {
                    self::onMessage($body, $type);
                }
            }
        }
    }

    private static function v2_split($bin, $total)
    {
        $list = [];
        $step = 0;
        $data = gzuncompress($bin);
        $total = strlen($data);
        while (true) {
            if ($step > 65535) exit();
            if ($step == $total) break;
            $bin = substr($data, $step, 16);
            $head = self::unPackMsg($bin);
            $length = isset($head['packlen']) ? $head['packlen'] : 16;
            $body = substr($data, $step + 16, $length - 16);
            $step += $length;
            array_push($list, $body);
        }
        return $list;
    }

    /*
     * @use replace delay by select
     */
    public static function Delayed()
    {
        $r = [];
        $w = NULL;
        $e = NULL;
        $delay = 0;
        if (self::getLock() > time())
            return new Delayed(1000);
        try {
            foreach (self::$client_maps as $client_info) {
                if ($client_info['client'])
                    $r[] = $client_info['client']->getResource();
            }
            if (count($r) !== 0)
                socket_select($r, $w, $e, 1);
            else
                $delay = 50;
        } catch (Exception $exception) {
            $delay = 1000;
        }
        return new Delayed($delay);
    }
}
