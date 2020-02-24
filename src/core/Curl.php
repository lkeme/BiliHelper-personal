<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Core;

class Curl
{
    public static $headers = array(
        'Accept' => '*/*',
        'Accept-Encoding' => 'gzip',
        'Accept-Language' => 'zh-cn',
        'Connection' => 'keep-alive',
        'Content-Type' => 'application/x-www-form-urlencoded',
        'User-Agent' => 'bili-universal/8470 CFNetwork/978.0.7 Darwin/18.5.0',
//        'Referer' => 'https://live.bilibili.com/',
    );

    private static $results = [];
    private static $result = [];

    /**
     * @use 数组
     * @return array
     */
    private static function getResults()
    {
        $results = self::$results;
        self::$results = [];
        return $results;
    }


    /**
     * @use 字符串or其他
     * @return array
     */
    private static function getResult()
    {
        $result = self::$result;
        self::$result = [];
        return array_shift($result);
    }

    /**
     * @use 获取Headers
     * @param $headers
     * @return array
     */
    private static function getHeaders($headers)
    {
        return array_map(function ($k, $v) {
            return $k . ': ' . $v;
        }, array_keys($headers), $headers);
    }


    /**
     * @use 初始化Curl
     * @param int $tasks_num
     * @param bool $bar
     * @return \Ares333\Curl\Curl
     */
    private static function getMultiClient(int $tasks_num = 1, bool $bar = false)
    {
        $toolkit = new \Ares333\Curl\Toolkit();
        $toolkit->setCurl();
        $curl = $toolkit->getCurl();
        $curl->maxThread = $tasks_num < 10 ? $tasks_num : 10;
        if (!$bar) {
            $curl->onInfo = null;
        }
        return $curl;
    }


    /**
     * @use 填充CURL_OPT
     * @param $url
     * @param $payload
     * @param $headers
     * @param $timeout
     * @return array
     */
    private static function fillCurlOpts($url, $payload, $headers, $timeout): array
    {
        $default_opts = [
            CURLOPT_URL => self::http2https($url),
            CURLOPT_HEADER => 0,
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_IPRESOLVE => 1,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => self::$headers['User-Agent'],
            CURLOPT_HTTPHEADER => is_null($headers) ? self::getHeaders(self::$headers) : self::getHeaders($headers),
            CURLOPT_COOKIE => getenv('COOKIE') != "" ? getenv('COOKIE') : "",
            // CURLOPT_CONNECTTIMEOUT => 10,
            // CURLOPT_AUTOREFERER => true,
            // CURLOPT_RETURNTRANSFER => true,
            // CURLOPT_FOLLOWLOCATION => true,
            // CURLOPT_SSL_VERIFYHOST => false,
            // CURLOPT_SSL_VERIFYPEER => false,
            // CURLOPT_MAXREDIRS => 5
        ];
        if ($payload) {
            $default_opts[CURLOPT_POST] = 1;
            $payload = is_bool($payload) ? [] : (is_array($payload) ? http_build_query($payload) : $payload);
            $default_opts[CURLOPT_POSTFIELDS] = $payload;
        }
        if (getenv('USE_PROXY') == 'true') {
            $default_opts[CURLOPT_PROXY] = getenv('PROXY_IP');
            $default_opts[CURLOPT_PROXYPORT] = getenv('PROXY_PORT');
        }
        return $default_opts;
    }

    /**
     * @use async
     * @param $url
     * @param array $tasks
     * @param null $headers
     * @param int $timeout
     * @return array
     */
    public static function asyncPost($url, $tasks = [], $headers = null, $timeout = 30)
    {
        $curl = self::getMultiClient(count($tasks));
        $curl_options = self::fillCurlOpts($url, true, $headers, $timeout);
        $curl->onTask = function ($curl) use ($curl_options, &$tasks) {
            $task = array_shift($tasks);
            if (is_null($task)) {
                return;
            }
            $payload = $task['payload'];
            $curl_options[CURLOPT_POSTFIELDS] = is_array($payload) ? http_build_query($payload) : $payload;
            $curl->add([
                'opt' => $curl_options,
                'args' => $task['source'],
            ], function ($response, $args) {
                Log::debug($response['info']['url']);
                array_push(self::$results, [
                    'content' => $response['body'],
                    'source' => $args
                ]);
                Log::debug($response['body']);
            });
        };
        $curl->start();
        return self::getResults();
    }

    /**
     * @use post
     * @param $url
     * @param $payload
     * @param null $headers
     * @param int $timeout
     * @return bool|string
     * @throws \Exception
     */
    public static function post($url, $payload = null, $headers = null, $timeout = 30)
    {
        $url = self::http2https($url);
        Log::debug($url);
        $header = is_null($headers) ? self::getHeaders(self::$headers) : self::getHeaders($headers);

        // 重试次数
        $ret_count = 300;
        $waring = 280;

        while ($ret_count) {
            // 网络断开判断 延时方便连接网络
            if ($ret_count < $waring) {
                Log::warning("正常等待网络连接状态恢复正常...");
                sleep(random_int(5, 10));
            }
            try {
                $curl = curl_init();
                if (!is_null($payload)) {
                    curl_setopt($curl, CURLOPT_POST, 1);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, is_array($payload) ? http_build_query($payload) : $payload);
                }
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
                curl_setopt($curl, CURLOPT_HEADER, 0);
                curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
                curl_setopt($curl, CURLOPT_IPRESOLVE, 1);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
                // 超时 重要
                curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
                if (($cookie = getenv('COOKIE')) != "") {
                    curl_setopt($curl, CURLOPT_COOKIE, $cookie);
                }
                if (getenv('USE_PROXY') == 'true') {
                    curl_setopt($curl, CURLOPT_PROXY, getenv('PROXY_IP'));
                    curl_setopt($curl, CURLOPT_PROXYPORT, getenv('PROXY_PORT'));
                }
                $raw = curl_exec($curl);

                if ($err_no = curl_errno($curl)) {
                    throw new \Exception(curl_error($curl));
                }

                if ($raw === false || strpos($raw, 'timeout') !== false) {
                    Log::warning('获取的资源无效!');
                    $ret_count--;
                    continue;
                }

                Log::debug($raw);
                curl_close($curl);
                return $raw;

            } catch (\Exception $e) {
                Log::warning("Curl请求出错, {$e->getMessage()}!");
                $ret_count--;
                continue;
            }
        }
        exit('重试次数过多，请检查代码，退出!');
    }

    /**
     * @use request
     * @param $url
     * @param null $payload
     * @param null $headers
     * @param null $cookie
     * @param int $timeout
     * @return bool|string
     */
    public static function request($url, $payload = null, $headers = null, $cookie = null, $timeout = 30)
    {
        Log::debug($url);
        $header = is_null($headers) ? self::getHeaders(self::$headers) : self::getHeaders($headers);

        // 重试次数
        $ret_count = 30;
        while ($ret_count) {
            try {
                $curl = curl_init();
                if (!is_null($payload)) {
                    curl_setopt($curl, CURLOPT_POST, 1);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, is_array($payload) ? http_build_query($payload) : $payload);
                }
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
                curl_setopt($curl, CURLOPT_HEADER, 0);
                curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
                curl_setopt($curl, CURLOPT_IPRESOLVE, 1);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
                // 超时 重要
                curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
                // 认证
                if (!is_null($cookie)) {
                    curl_setopt($curl, CURLOPT_COOKIE, $cookie);
                }
                if (getenv('USE_PROXY') == 'true') {
                    curl_setopt($curl, CURLOPT_PROXY, getenv('PROXY_IP'));
                    curl_setopt($curl, CURLOPT_PROXYPORT, getenv('PROXY_PORT'));
                }
                $raw = curl_exec($curl);

                if ($err_no = curl_errno($curl)) {
                    throw new \Exception(curl_error($curl));
                }

                if ($raw === false || strpos($raw, 'timeout') !== false) {
                    Log::warning('获取的资源无效!');
                    $ret_count--;
                    continue;
                }

                Log::debug($raw);
                curl_close($curl);
                return $raw;

            } catch (\Exception $e) {
                Log::warning("Curl请求出错, {$e->getMessage()}!");
                $ret_count--;
                continue;
            }
        }
        exit('重试次数过多，请检查代码，退出!');
    }


    /**
     * @use get
     * @param $url
     * @param null $payload
     * @param null $headers
     * @return bool|string
     * @throws \Exception
     */
    public static function get($url, $payload = null, $headers = null)
    {
        if (!is_null($payload)) {
            $url .= '?' . http_build_query($payload);
        }
        return self::post($url, null, $headers);
    }


    /**
     * @use 单次请求
     * @param $method
     * @param $url
     * @param array $payload
     * @param array $headers
     * @param int $timeout
     * @return false|string
     */
    public static function singleRequest($method, $url, $payload = [], $headers = [], $timeout = 10)
    {
        $url = self::http2https($url);
        Log::debug($url);
        $options = array(
            'http' => array(
                'method' => strtoupper($method),
                'header' => self::arr2str($headers),
                'content' => http_build_query($payload),
                'timeout' => $timeout,
            ),
        );
        $result = @file_get_contents($url, false, stream_context_create($options));
        Log::debug($result);
        return $result ? $result : null;
    }

    /**
     * @use 关联数组转字符串
     * @param array $array
     * @param string $separator
     * @return string
     */
    private static function arr2str(array $array, string $separator = "\r\n"): string
    {
        $tmp = '';
        foreach ($array as $key => $value) {
            $tmp .= "{$key}:{$value}{$separator}";
        }
        return $tmp;
    }


    /**
     * @use http(s)转换
     * @param string $url
     * @return string
     */
    private static function http2https(string $url): string
    {
        switch (getenv('USE_HTTPS')) {
            case 'false':
                if (strpos($url, 'ttps://')) {
                    $url = str_replace('https://', 'http://', $url);
                }
                break;
            case 'true':
                if (strpos($url, 'ttp://')) {
                    $url = str_replace('http://', 'https://', $url);
                }
                break;
            default:
                Log::warning('当前协议设置不正确,请检查配置文件!');
                die();
                break;
        }

        return $url;
    }
}
