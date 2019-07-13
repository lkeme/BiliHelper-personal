<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;

class Socket
{
    protected static $socket_connection = null;
    protected static $ips = [];
    protected static $socket_ip = null;
    protected static $socket_port = null;
    public static $lock = 0;
    protected static $heart_lock = 0;

    // RUN
    public static function run()
    {
        if (self::$lock > time()) {
            return;
        }
        self::$lock = time() + 0.5;

        self::start();
        $message = self::decodeMessage();
        if (!$message) {
            unset($message);
            self::resetConnection();
            return;
        }
        $data = DataTreating::socketJsonToArray($message);
        if (!$data) {
            return;
        }
        DataTreating::socketArrayToDispose($data);
        return;
    }

    // KILL
    protected static function killConnection()
    {
        socket_clear_error(self::$socket_connection);
        socket_shutdown(self::$socket_connection);
        socket_close(self::$socket_connection);
        self::$socket_connection = null;
    }

    // RECONNECT
    protected static function resetConnection()
    {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        unset($errormsg);
        unset($errorcode);
        self::killConnection();
//        Log::warning('SOCKET连接断开,5秒后重新连接...');
//        sleep(5);
        self::start();
        return;
    }

    // SOCKET READER
    protected static function readerSocket(int $length)
    {
        return socket_read(self::$socket_connection, $length);
    }

    // DECODE MESSAGE
    protected static function decodeMessage()
    {
        $res = '';
        $tmp = '';
        while (1) {
            while ($out = self::readerSocket(16)) {
                $res = unpack('N', $out);
                unset($out);
                if ($res[1] != 16) {
                    break;
                }
            }
            if (isset($res[1])) {
                $length = $res[1] - 16;
                if ($length > 65535) {
                    continue;
                }
                if ($length <= 0) {
                    return false;
                }
                return self::readerSocket($length);
            }
            return false;
        }
    }

    // START
    protected static function start()
    {
        if (is_null(self::$socket_connection)) {
            $room_id = empty(getenv('SOCKET_ROOM_ID')) ? Live::getUserRecommend() : Live::getRealRoomID(getenv('SOCKET_ROOM_ID'));
            $room_id = intval($room_id);
            if ($room_id) {
                self::getSocketServer($room_id);
                self::connectServer($room_id, self::$socket_ip, self::$socket_port);
            }
        }
        self::sendHeartBeatPkg();
        return;
    }

    // SEND HEART
    protected static function sendHeartBeatPkg()
    {
        if (self::$heart_lock > time()) {
            return;
        }
        $action_heart_beat = intval(getenv('ACTIONHEARTBEAT'));
        $str = pack('NnnNN', 16, 16, 1, $action_heart_beat, 1);
        socket_write(self::$socket_connection, $str, strlen($str));
        Log::info('发送心跳包到弹幕服务器!');
        self::$heart_lock = time() + 30;
        return;
    }

    // SOCKET CONNECT
    protected static function connectServer($room_id, $ip, $port)
    {
        $falg = 10;
        while ($falg) {
            try {
                $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                socket_connect($socket, $ip, $port);
                $str = self::packMsg($room_id);
                socket_write($socket, $str, strlen($str));
                self::$socket_connection = $socket;
                // TODO
                Log::info("连接到弹幕服务器[{$room_id}]成功!");
                return;
            } catch (\Exception $e) {
                Log::info("连接到弹幕服务器[{$room_id}]失败!");
                Log::warning($e);
                $falg -= 1;
            }
        }
        Log::info("连接弹幕服务器[{$room_id}]错误次数过多，检查网络!");
        exit();
    }

    // PACK DATA
    protected static function packMsg($room_id)
    {
        $action_entry = intval(getenv('ACTIONENTRY'));
        $data = sprintf("{\"uid\":%d%08d,\"roomid\":%d}",
            random_int(1000000, 2999999),
            random_int(0, 99999999),
            intval($room_id)
        );
        return pack('NnnNN', 16 + strlen($data), 16, 1, $action_entry, 1) . $data;
    }

    // GET SERVER
    protected static function getSocketServer(int $room_id): bool
    {
        while (1) {
            try {
                $payload = [
                    'room_id' => $room_id,
                ];
                $data = Curl::get('https://api.live.bilibili.com/room/v1/Danmu/getConf', Sign::api($payload));
                $data = json_decode($data, true);

                // TODO 判断
                if (isset($data['code']) && $data['code']) {
                    continue;
                }

                self::$socket_ip = gethostbyname($data['data']['host']);
                self::$socket_port = $data['data']['port'];

                break;
            } catch (\Exception $e) {
                Log::warning("获取弹幕服务器出错，错误信息[{$e}]!");
                continue;
            }
        }
        return true;
    }
}