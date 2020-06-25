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
    private static $client;
    private static $async_opt;
    private static $results = [];
    private static $result = [];

    /**
     * @use POST请求
     * @param $os
     * @param $url
     * @param array $params
     * @param array $headers
     * @param int $timeout
     * @return mixed
     */
    public static function post($os, $url, $params = [], $headers = [], $timeout = 30)
    {
        Log::debug("POST: {$url}");
        $headers = self::getHeaders($os, $headers);
        $payload['form_params'] = count($params) ? $params : [];
        $options = self::getClientOpt($payload, $headers);
        $request = self::clientHandle($url, 'post', $options);
        $body = $request->getBody();
        Log::debug($body);
        return $body;
    }

    /**
     * @use GET请求
     * @param $os
     * @param $url
     * @param array $params
     * @param array $headers
     * @param int $timeout
     * @return mixed
     */
    public static function get($os, $url, $params = [], $headers = [], $timeout = 30)
    {
        Log::debug("GET: {$url}");
        $headers = self::getHeaders($os, $headers);
        $payload['query'] = count($params) ? $params : [];
        $options = self::getClientOpt($payload, $headers);
        $request = self::clientHandle($url, 'get', $options);
        $body = $request->getBody();
        Log::debug($body);
        return $body;
    }

    /**
     * @use PUT请求
     * @param $os
     * @param $url
     * @param array $params
     * @param array $headers
     * @param int $timeout
     * @return mixed
     */
    public static function put($os, $url, $params = [], $headers = [], $timeout = 30)
    {
        Log::debug("PUT: {$url}");
        $headers = self::getHeaders($os, $headers);
        $payload['json'] = count($params) ? $params : [];
        $options = self::getClientOpt($payload, $headers);
        $request = self::clientHandle($url, 'post', $options);
        $body = $request->getBody();
        Log::debug($body);
        return $body;
    }

    /**
     * @use 并发POST请求
     * @param $os
     * @param $url
     * @param array $tasks
     * @param array $headers
     * @param int $timeout
     * @return array
     */
    public static function async($os, $url, $tasks = [], $headers = [], $timeout = 30)
    {
        self::$async_opt = [
            'tasks' => $tasks,
            'counter' => 1,
            'count' => count($tasks),
            'concurrency' => count($tasks) < 10 ? count($tasks) : 10
        ];
        Log::debug("ASYNC: {$url}");
        $headers = self::getHeaders($os, $headers);
        $requests = function ($total) use ($url, $headers, $tasks) {
            foreach ($tasks as $task) {
                yield function () use ($url, $headers, $task) {
                    $payload['form_params'] = $task['payload'];
                    $options = self::getClientOpt($payload, $headers);
                    return self::clientHandle($url, 'postAsync', $options);
                };
            }
        };
        $pool = new \GuzzleHttp\Pool(self::$client, $requests(self::$async_opt['count']), [
            'concurrency' => self::$async_opt['concurrency'],
            'fulfilled' => function ($response, $index) {
                $res = $response->getBody();
                // Log::notice("启动多线程 {$index}");
                array_push(self::$results, [
                    'content' => $res,
                    'source' => self::$async_opt['tasks'][$index]['source']
                ]);
                self::countedAndCheckEnded();
            },
            'rejected' => function ($reason, $index) {
                Log::error("多线程第{$index}个请求失败, ERROR: {$reason}");
                self::countedAndCheckEnded();
            },
        ]);
        // 开始发送请求
        $promise = $pool->promise();
        $promise->wait();
        return self::getResults();
    }

    /**
     * @use 单次请求
     * @param $method
     * @param $url
     * @param array $payload
     * @param array $headers
     * @param int $timeout
     * @return false|string|null
     */
    public static function request($method, $url, $payload = [], $headers = [], $timeout = 10)
    {
        Log::debug("REQUEST: {$url}");
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
     * @use 计数搭配并发使用
     */
    private static function countedAndCheckEnded()
    {
        if (self::$async_opt['counter'] < self::$async_opt['count']) {
            self::$async_opt['counter']++;
            return;
        }
        # 请求结束！
        self::$async_opt = [];
    }


    /**
     * @use 请求中心异常处理
     * @param string $url
     * @param string $method
     * @param array $options
     * @return mixed
     * @throws \Exception
     */
    private static function clientHandle(string $url, string $method, array $options)
    {
        $max_retry = range(1, 40);
        foreach ($max_retry as $retry) {
            try {
                $response = call_user_func_array([self::$client, $method], [$url, $options]);
                if (is_null($response) or empty($response)) throw new \Exception("Value IsEmpty");
                return $response;
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                // var_dump($e->getRequest());
                if ($e->hasResponse()) var_dump($e->getResponse());
            } catch (\Exception $e) {
                // var_dump($e);
            }
            Log::warning("CURl -> RETRY: {$retry} ERROR: {$e->getMessage()} ERRNO: {$e->getCode()} STATUS:  Waiting for recovery!");
            sleep(15);
        }
        exit('网络异常，超出最大尝试次数，退出程序~');
    }

    /**
     * @use 获取请求配置
     * @param array $add_options
     * @param array $headers
     * @param float $timeout
     * @return array
     */
    private static function getClientOpt(array $add_options, array $headers = [], float $timeout = 30.0)
    {
        self::$client = new \GuzzleHttp\Client();
        $default_options = [
            'headers' => $headers,
            'timeout' => $timeout,
            'http_errors' => false,
            'verify' => getenv('VERIFY_SSL') == 'false' ? false : true,
        ];
        if (getenv('USE_PROXY') == 'true') {
            $default_options['proxy'] = getenv('NETWORK_PROXY');
        }
        return array_merge($default_options, $add_options);
    }

    /**
     * @use 获取Headers
     * @param string $os
     * @param array $headers
     * @return array
     */
    private static function getHeaders(string $os = 'app', array $headers = []): array
    {
        $app_headers = [
            'Accept' => '*/*',
            'Accept-Encoding' => 'gzip',
            'Accept-Language' => 'zh-cn',
            'Connection' => 'keep-alive',
            // 'Content-Type' => 'application/x-www-form-urlencoded',
            // 'User-Agent' => 'Mozilla/5.0 BiliDroid/5.51.1 (bbcallen@gmail.com)',
            'User-Agent' => 'Mozilla/5.0 BiliDroid/6.3.0 (bbcallen@gmail.com) os/android model/MuMu mobi_app/android build/6030600 channel/bili innerVer/6030600 osVer/6.0.1 network/2',
            // 'Referer' => 'https://live.bilibili.com/',
        ];
        $pc_headers = [
            'Accept' => "application/json, text/plain, */*",
            'Accept-Encoding' => 'gzip, deflate',
            'Accept-Language' => "zh-CN,zh;q=0.9",
            // 'Content-Type' => 'application/x-www-form-urlencoded',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/82.0.4056.0 Safari/537.36 Edg/82.0.431.0',
            // 'Referer' => 'https://live.bilibili.com/',
        ];
        $other_headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36',
        ];
        $default_headers = isset(${$os . "_headers"}) ? ${$os . "_headers"} : $other_headers;
        if (in_array($os, ['app', 'pc']) && getenv('COOKIE') != "") {
            $default_headers['Cookie'] = getenv('COOKIE');
        }
        // return self::formatHeaders(array_merge($default_headers, $headers));
        return array_merge($default_headers, $headers);
    }

    /**
     * @use 格式化Headers
     * @param $headers
     * @return array
     */
    private static function formatHeaders(array $headers): array
    {
        return array_map(function ($k, $v) {
            return $k . ': ' . $v;
        }, array_keys($headers), $headers);
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
     * @use 数组
     * @return array
     */
    private static function getResults(): array
    {
        $results = self::$results;
        self::$results = [];
        return $results;
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
     * @use GET请求
     * @param $os
     * @param $url
     * @param array $params
     * @param array $headers
     * @param int $timeout
     * @return mixed
     */
    public static function headers($os, $url, $params = [], $headers = [], $timeout = 30)
    {
        Log::debug('HEADERS: ' . $url);
        $headers = self::getHeaders($os, $headers);
        $payload['query'] = count($params) ? $params : [];
        $payload['allow_redirects'] = false;
        $options = self::getClientOpt($payload, $headers);
        $request = self::clientHandle($url, 'get', $options);
        Log::debug("获取Headers");
        return $request->getHeaders();
    }
}
