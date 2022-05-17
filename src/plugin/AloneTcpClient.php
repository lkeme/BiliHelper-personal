<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Env;
use BiliHelper\Core\Log;
use BiliHelper\Util\TimeLock;

use Exception;
use Socket\Raw\Factory;
use Socket\Raw\Socket;

class AloneTcpClient
{
    use TimeLock;

    private static int $heart_lock = 0;
    private static ?Socket $client = null;
    private static string|null $server_addr = null;
    private static string|null $server_key = null;
    private static int $socket_timeout = 0;
    private static int $max_errors_num = 0; // 最大连续错误5次

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('alone_monitor')) {
            return;
        }
        self::setPauseStatus();
        self::init();
        self::heartBeat();
        self::receive();
    }

    /**
     * @use 初始化
     */
    private static function init(): void
    {
        if (empty(getConf('server_addr', 'alone_monitor')) || empty(getConf('server_key', 'alone_monitor'))) {
            Env::failExit('推送服务器信息不完整, 请检查配置文件!');
        }
        if (!self::$server_addr || !self::$server_key) {
            self::$server_addr = getConf('server_addr', 'alone_monitor');
            self::$server_key = getConf('server_key', 'alone_monitor');
        }
        if (!self::$client) {
            self::openConnect();
        }
    }

    /**
     * @use 数据封装
     * @param $value
     * @param string $fmt
     * @return string
     */
    private static function packMsg($value, string $fmt = "N"): string
    {
        $head = pack($fmt, strlen($value));
        return $head . $value;
    }

    /**
     * @use 数据解包
     * @param $value
     * @param string $fmt
     * @return mixed
     * @throws Exception
     */
    private static function unPackMsg($value, string $fmt = "N"): mixed
    {
        return unpack($fmt, $value)[1];
    }

    /**
     * @use 连接认证
     */
    private static function handShake(): void
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
     * @use 心跳
     */
    private static function heartBeat(): void
    {
        if (self::$heart_lock <= time()) {
            if (self::writer("")) {
                // 心跳默认35s 调整数据错开错误
                self::$heart_lock = time() + 25;
            }
        }
    }

    /**
     * @use 读数据
     * @param $length
     * @return mixed
     */
    private static function reader($length): mixed
    {
        $data = false;
        try {
            while (self::$client->selectRead(self::$socket_timeout)) {
                $data = self::$client->read($length);
                if (!$data || strlen($data) > 65535 || strlen($data) < 0) {
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
     * @use 写数据
     * @param $data
     * @return bool
     */
    private static function writer($data): bool
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
     * @use 打开连接
     */
    private static function openConnect(): void
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
                self::setLock(60);
            }
        }
    }

    /**
     * @use 重新连接
     */
    private static function reConnect(): void
    {
        Log::info('重新连接到推送服务器');
        self::closeConnect();
        self::openConnect();
    }

    /**
     * @use 断开连接
     */
    private static function closeConnect(): void
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
    private static function receive(): void
    {
        $len_body = self::reader(4);
        if (!$len_body) {
            // 长度为0 ，空信息
            return;
        }
        Log::debug("(len=$len_body)");
        $body = self::reader($len_body);
        $raw_data = json_decode($body, true);
        // 数据可能出现不全 DECODE返回NULL 索引错误 交给default处理
        $data_type = is_null($raw_data) ? "unknown" : $raw_data['type'];
        switch ($data_type) {
            case 'raffle':
                // 抽奖推送
                Log::debug("(receive=$body)");
                DataTreating::distribute($raw_data['data']);
                break;
            case 'entered':
                // 握手确认
                Log::info("确认到推送服务器 {$raw_data['type']}");
                self::$max_errors_num = 0;
                break;
            case 'error':
                // 产生错误
                Log::error("推送服务器异常 {$raw_data['data']['msg']}, 程序错误5次后将挂起, 请手动关闭!");
                if (self::$max_errors_num == 5) {
                    // KEY到期推送提醒
                    Notice::push('key_expired');
                    // 程序挂起 防止systemd无限重启导致触发过多推送提醒
                    sleep(86400);
                    exit();
                }
                self::$max_errors_num += 1;
                break;
            case 'heartbeat':
                // 服务端心跳推送
                // Log::info("推送服务器心跳推送 {$body}");
                Log::debug("(heartbeat={$raw_data['data']['now']})");
                break;
            case 'sleep':
                // 服务器发布命令
                Log::warning("服务器发布休眠命令 {$raw_data['data']['msg']}");
                sleep($raw_data['data']['hour']);
                break;
            case 'update':
                // 服务器发布命令
                Log::notice("服务器发布更新命令 {$raw_data['data']['msg']}");
                Notice::push('update', $raw_data['data']['msg']);
                break;
            case 'exit':
                // 服务器发布命令
                Env::failExit("服务器发布退出命令 {$raw_data['data']['msg']}");
            default:
                // 未知信息
                var_dump($raw_data);
                Log::info("出现未知信息 $body");
                // 出现未知信息 处理重连 防止内存益处
                self::reConnect();
                break;
        }
    }

    /**
     * @use 写入log
     * @param $message
     */
    private static function writeLog($message): void
    {
        $path = './danmu/';
        if (!file_exists($path)) {
            mkdir($path);
            chmod($path, 0777);
        }
        $filename = $path . getConf('username', 'login.account') . ".log";
        $date = date('[Y-m-d H:i:s] ');
        $data = "[$date]$message" . PHP_EOL;
        file_put_contents($filename, $data, FILE_APPEND);
    }
}