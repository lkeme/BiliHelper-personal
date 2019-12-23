<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019 ~ 2020
 */

namespace lkeme\BiliHelper;

use Exception;
use Socket\Raw\Factory;

class TcpClient
{
    public static $lock = 0;
    private static $heart_lock = 0;
    private static $client = null;
    private static $server_addr = null;
    private static $server_key = null;
    private static $socket_timeout = 0;

    /**
     * @desc 入口
     */
    public static function run()
    {
        if (self::$lock > time() || getenv('USE_SERVER') == 'false') {
            return;
        }
        self::init();
        self::heartBeat();
        self::receive();
    }


    /**
     * @desc 初始化
     */
    private static function init()
    {
        if (empty(getenv('SERVER_ADDR')) || empty(getenv('SERVER_KEY'))) {
            exit('推送服务器信息不完整, 请检查配置文件!');
        }
        if (!self::$server_addr || !self::$server_key) {
            self::$server_addr = getenv('SERVER_ADDR');
            self::$server_key = getenv('SERVER_KEY');
        }
        if (!self::$client) {
            self::openConnect();
        }
    }


    /**
     * @desc 数据封装
     * @param $value
     * @param $fmt
     * @return string
     */
    private static function packMsg($value, $fmt = "N")
    {
        $head = pack($fmt, strlen($value));
        $data = $head . $value;
        return $data;
    }

    /**
     * @desc 数据解包
     * @param $value
     * @param string $fmt
     * @return array|false
     */
    private static function unPackMsg($value, $fmt = "N")
    {
        $data = unpack($fmt, $value);
        return $data[1];
    }

    /**
     * @desc 连接认证
     */
    private static function handShake()
    {
        self::writer(
            json_encode([
                'code' => 0,
                'type' => 'ask',
                'data' => [
                    'key' => self::$server_key,
                ]
            ])
        );
    }

    /**
     * @desc 心跳
     */
    private static function heartBeat()
    {
        if (self::$heart_lock <= time()) {
            if (self::writer("")) {
                // 心跳默认35s 调整数据错开错误
                self::$heart_lock = time() + 25;
            }
        }
    }

    /**
     * @desc 读数据
     * @param $length
     * @return array|bool|false
     */
    private static function reader($length)
    {
        $data = false;
        try {
            while (self::$client->selectRead(self::$socket_timeout)) {
                $data = self::$client->read($length);
                if (!$data) {
                    throw new Exception("Connection failure");
                }
                if ($length == 4) $data = self::unPackMsg($data);
                break;
            }
        } catch (Exception $exception) {
            self::reConnect();
        }
        return $data;
    }

    /**
     * @desc 写数据
     * @param $data
     * @return bool
     */
    private static function writer($data)
    {
        $status = false;
        try {
            while (self::$client->selectWrite(self::$socket_timeout)) {
                $data = self::packMsg($data);
                $status = self::$client->write($data);
                break;
            }
        } catch (Exception $exception) {
            self::reConnect();
        }
        return $status;
    }


    /**
     * @desc 打开连接
     */
    private static function openConnect()
    {
        if (!self::$client) {
            try {
                $socket = (new Factory())->createClient(self::$server_addr, 40);
                $socket->setBlocking(false);
                self::$client = $socket;
                self::handShake();
                Log::info("连接到 {$socket->getPeerName()} 推送服务器");
            } catch (Exception $e) {
                Log::error("连接到推送服务器失败, {$e->getMessage()}");
                self::$lock = time() + 60;
            }
        }
    }

    /**
     * @desc 重新连接
     */
    private static function reConnect()
    {
        Log::info('重新连接到推送服务器');
        self::closeConnect();
        self::openConnect();
    }

    /**
     * @desc 断开连接
     */
    private static function closeConnect()
    {
        Log::info('断开推送服务器');
        try {
            self::$client->shutdown();
            self::$client->close();
        } catch (Exception $exception) {
            // var_dump($exception);
        }
        self::$client = null;
    }


    /**
     * @use 读取数据
     */
    private static function receive()
    {
        $len_body = self::reader(4);
        if (!$len_body) {
            // 长度为0 ，空信息
            return;
        }
        Log::debug("(len=$len_body)");
        $body = self::reader($len_body);
        $raw_data = json_decode($body, true);
        // 人气值(或者在线人数或者类似)以及心跳
        $data_type = $raw_data['type'];
        switch ($data_type) {
            case 'raffle':
                // 抽奖推送
                // Log::notice($body);
                DataTreating::distribute($raw_data['data']);
                break;
            case 'entered':
                // 握手确认
                Log::info("确认到推送服务器 {$raw_data['type']}");
                break;
            case 'error':
                // 致命错误
                Log::error("推送服务器发生致命错误 {$raw_data['data']['msg']}");
                exit();
                break;
            case 'heartbeat':
                // 服务端心跳推送
                // Log::info("推送服务器心跳推送 {$body}");
                Log::debug("(heartbeat={$raw_data['data']['now']})");
                break;
            default:
                // 未知信息
                var_dump($raw_data);
                Log::info("出现未知信息 {$body}");
                break;
        }
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