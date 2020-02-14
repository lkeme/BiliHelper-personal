<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Core;

use CurlFuture\HttpFuture;

class Curl
{
    public static $headers = array(
        'Accept' => '*/*',
        'Accept-Encoding' => 'gzip',
        'Accept-Language' => 'zh-cn',
        'Connection' => 'keep-alive',
        'Content-Type' => 'application/x-www-form-urlencoded',
        'User-Agent' => 'bili-universal/8470 CFNetwork/978.0.7 Darwin/18.5.0',
        // 'Referer' => 'https://live.bilibili.com/',
    );

    private static function getHeaders($headers)
    {
        return array_map(function ($k, $v) {
            return $k . ': ' . $v;
        }, array_keys($headers), $headers);
    }

    public static function asyncPost($url, $tasks = null, $headers = null, $timeout = 30)
    {
        $new_tasks = [];
        $results = [];
        $url = self::http2https($url);
        $curl_options = [
            CURLOPT_HEADER => 0,
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_IPRESOLVE => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
        ];
        if (($cookie = getenv('COOKIE')) != "") {
            $curl_options[CURLOPT_COOKIE] = $cookie;
        }
        if (getenv('USE_PROXY') == 'true') {
            $curl_options[CURLOPT_PROXY] = getenv('PROXY_IP');
            $curl_options[CURLOPT_PROXYPORT] = getenv('PROXY_PORT');
        }
        foreach ($tasks as $task) {
            $payload = $task['payload'];
            $header = is_null($headers) ? self::getHeaders(self::$headers) : self::getHeaders($headers);
            $options = [
                'header' => $header,
                'post_data' => is_array($payload) ? http_build_query($payload) : $payload
            ];
            $new_task = new HttpFuture($url, $options, $curl_options);
            array_push($new_tasks, [
                'task' => $new_task,
                'source' => $task['source']
            ]);
        }
        foreach ($new_tasks as $new_task) {
            Log::debug($url);
            $result = $new_task['task']->fetch();
            // var_dump($result);
            array_push($results, [
                'content' => $result,
                'source' => $new_task['source']
            ]);
            Log::debug($result);
        }
        return $results;
    }

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
                    Log::warning('重试，获取的资源无效!');
                    $ret_count--;
                    continue;
                }

                Log::debug($raw);
                curl_close($curl);
                return $raw;

            } catch (\Exception $e) {
                Log::warning("重试,Curl请求出错,{$e->getMessage()}!");
                $ret_count--;
                continue;
            }
        }
        exit('重试次数过多，请检查代码，退出!');
    }

    public static function other($url, $payload = null, $headers = null, $cookie = null, $timeout = 30)
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
                    Log::warning('重试，获取的资源无效!');
                    $ret_count--;
                    continue;
                }

                Log::debug($raw);
                curl_close($curl);
                return $raw;

            } catch (\Exception $e) {
                Log::warning("重试,Curl请求出错,{$e->getMessage()}!");
                $ret_count--;
                continue;
            }
        }
        exit('重试次数过多，请检查代码，退出!');
    }


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
