<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;

use Wrench\Client;

class Websocket
{
    public static $lock = 0;
    protected static $heart_lock = 0;
    protected static $websocket = null;
    protected static $room_id = 0;


    /**
     * @use 入口
     */
    public static function run()
    {
        if (static::$lock > time()) {
            return;
        }
        static::init();
        static::heart();
        static::receive();

    }


    /**
     * @use 初始化
     */
    protected static function init()
    {
        if (!static::$websocket) {
            $client = new Client(
                'ws://broadcastlv.chat.bilibili.com:2244/sub',
                'http://live.bilibili.com'
            );
            static::$websocket = $client;
        }

        if (!static::$room_id) {
            static::$room_id = empty(getenv('SOCKET_ROOM_ID')) ? Live::getUserRecommend() : Live::getRealRoomID(getenv('SOCKET_ROOM_ID'));
        }
        return;
    }

    /**
     * @use 连接
     */
    protected static function connect()
    {
        Log::info("连接弹幕服务器");
        if (!static::$websocket->connect()) {
            Log::error('连接弹幕服务器失败');
            static::$lock = time() + 60;
            return;
        }
        static::$websocket->sendData(
            static::packMsg(json_encode([
                'uid' => 0,
                'roomid' => static::$room_id,
                'protover' => 1,
                'platform' => 'web',
                'clientver' => '1.4.1',
            ]), 0x0007)
        );
    }

    /**
     * @use 断开连接
     */
    protected static function disconnect()
    {
        Log::info('断开弹幕服务器');
        static::$websocket->disconnect();
    }


    /**
     * @use 读取数据
     */
    protected static function receive()
    {
        $responses = static::$websocket->receive();
        if (is_array($responses) && !empty($responses)) {
            foreach ($responses as $response) {
                static::split($response->getPayload());
            }
            static::receive();
        }

    }

    /**
     * @use 发送心跳
     */
    protected static function heart()
    {
        if (!static::$websocket->isConnected()) {
            static::connect();
            return;
        }
        if (static::$heart_lock <= time()) {
            if (static::$websocket->sendData(static::packMsg('', 0x0002))) {
                static::$heart_lock = time() + 30;
            }
        }
        return;
    }


    /**
     * @param $id
     * @return mixed|string
     */
    protected static function type($id)
    {
        $option = [
            0x0002 => 'WS_OP_HEARTBEAT',
            0x0003 => 'WS_OP_HEARTBEAT_REPLY',
            0x0005 => 'WS_OP_MESSAGE',
            0x0007 => 'WS_OP_USER_AUTHENTICATION',
            0x0008 => 'WS_OP_CONNECT_SUCCESS',
        ];
        return isset($option[$id]) ? $option[$id] : "WS_OP_UNKNOW($id)";
    }


    /**
     * @param $bin
     * @throws \Exception
     */
    protected static function split($bin)
    {
        if (strlen($bin)) {
            $head = unpack('Npacklen/nheadlen/nver/Nop/Nseq', substr($bin, 0, 16));
            $bin = substr($bin, 16);

            $length = isset($head['packlen']) ? $head['packlen'] : 16;
            $type = isset($head['op']) ? $head['op'] : 0x0000;
            $body = substr($bin, 0, $length - 16);

            Log::debug(static::type($type) . " (len=$length)");

            if (($length - 16) > 65535 || ($length - 16) < 0) {
                Log::notice("长度{$length}异常，重新连接服务器!");
                if (static::$websocket->isConnected()) {
                    static::disconnect();
                }
                if (!static::$websocket->isConnected()) {
                    static::connect();
                }
                return;
            }

            if ($type == 0x0005 || $type == 0x0003) {
                if ($head['ver'] == 2) {
                    $body = gzuncompress($body);
                    if (substr($body, 0, 1) != '{') {
                        static::split($bin);
                        return;
                    }
                }
                DataTreating::socketJsonToArray($body);
            }

            $bin = substr($bin, $length - 16);
            if (strlen($bin)) {
                static::split($bin);
            }
        }
        return;
    }


    /**
     * @param $value
     * @param $option
     * @return string
     * @throws \Exception
     */
    protected static function packMsg($value, $option)
    {
        $head = pack('NnnNN', 0x10 + strlen($value), 0x10, 0x01, $option, 0x0001);
        $str = $head . $value;
        static::split($str);
        return $str;
    }

    /**
     * @use 写入log
     * @param $message
     */
    private static function writeLog($message)
    {
        $path = './danmu/';
        if (!file_exists($path)) {
            mkdir($path);
            chmod($path, 0777);
        }
        $filename = $path . getenv('APP_USER') . ".log";
        $date = date('[Y-m-d H:i:s] ');
        $data = "[{$date}]{$message}" . PHP_EOL;
        file_put_contents($filename, $data, FILE_APPEND);
        return;
    }
}