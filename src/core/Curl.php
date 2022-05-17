<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Core;

use Exception;
use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use BiliHelper\Tool\Generator;
use GuzzleHttp\Exception\RequestException;

class Curl
{
    private static Client $client;
    private static array $async_opt;
    private static array $results = [];
    private static array $result = [];
    private static string $buvid = '';

    /**
     * @use POST请求
     * @param $os
     * @param $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return mixed
     */
    public static function post($os, $url, array $params = [], array $headers = [], float $timeout = 30.0): mixed
    {
        Log::debug("POST: $url");
        $headers = self::getHeaders($os, $headers);
        $payload['form_params'] = count($params) ? $params : [];
        $options = self::getClientOpt($payload, $headers, $timeout);
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
     * @param float $timeout
     * @return mixed
     */
    public static function get($os, $url, array $params = [], array $headers = [], float $timeout = 30.0): mixed
    {
        Log::debug("GET: $url");
        $headers = self::getHeaders($os, $headers);
        $payload['query'] = count($params) ? $params : [];
        $options = self::getClientOpt($payload, $headers, $timeout);
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
     * @param float $timeout
     * @return mixed
     */
    public static function put($os, $url, array $params = [], array $headers = [], float $timeout = 30.0): mixed
    {
        Log::debug("PUT: $url");
        $headers = self::getHeaders($os, $headers);
        $payload['json'] = count($params) ? $params : [];
        $options = self::getClientOpt($payload, $headers, $timeout);
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
     * @param float $timeout
     * @return array
     */
    public static function async($os, $url, array $tasks = [], array $headers = [], float $timeout = 30.0): array
    {
        self::$async_opt = [
            'tasks' => $tasks,
            'counter' => 1,
            'count' => count($tasks),
            'concurrency' => min(count($tasks), 10)
        ];
        Log::debug("ASYNC: $url");
        $headers = self::getHeaders($os, $headers);
        $requests = function ($total) use ($url, $headers, $tasks, $timeout) {
            foreach ($tasks as $task) {
                yield function () use ($url, $headers, $task, $timeout) {
                    $payload['form_params'] = $task['payload'];
                    $options = self::getClientOpt($payload, $headers, $timeout);
                    return self::clientHandle($url, 'postAsync', $options);
                };
            }
        };
        $pool = new Pool(self::$client, $requests(self::$async_opt['count']), [
            'concurrency' => self::$async_opt['concurrency'],
            'fulfilled' => function ($response, $index) {
                $res = $response->getBody();
                // Log::notice("启动多线程 {$index}");
                self::$results[] = [
                    'content' => $res,
                    'source' => self::$async_opt['tasks'][$index]['source']
                ];
                self::countedAndCheckEnded();
            },
            'rejected' => function ($reason, $index) {
                Log::error("多线程第 $index 个请求失败, ERROR: $reason");
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
    public static function request($method, $url, array $payload = [], array $headers = [], int $timeout = 10): bool|string|null
    {
        Log::debug("REQUEST: $url");
        $options = array(
            'http' => array(
                'method' => strtoupper($method),
                'header' => self::arr2str($headers),
                'content' => http_build_query($payload),
                'timeout' => $timeout,
            ),
        );
        $result = $url ? @file_get_contents($url, false, stream_context_create($options)) : null;
        Log::debug($result);
        return $result ?: null;
    }

    /**
     * @use 计数搭配并发使用
     */
    private static function countedAndCheckEnded(): void
    {
        if (self::$async_opt['counter'] < self::$async_opt['count']) {
            self::$async_opt['counter']++;
            return;
        }
        // 请求结束
        self::$async_opt = [];
    }

    /**
     * @use 请求中心异常处理
     * @param string $url
     * @param string $method
     * @param array $options
     * @return mixed
     */
    private static function clientHandle(string $url, string $method, array $options): mixed
    {
        $max_retry = range(1, 40);
        foreach ($max_retry as $retry) {
            try {
                $response = call_user_func_array([self::$client, $method], [$url, $options]);
                if (is_null($response) or empty($response)) throw new Exception("Value IsEmpty");
                return $response;
            } catch (RequestException $e) {
                // var_dump($e->getRequest());
                if ($e->hasResponse()) var_dump($e->getResponse());
            } catch (Exception $e) {
                // $e->getHandlerContext()
                // var_dump($e);
            }
            Log::warning("Target -> URL: $url METHOD: $method");
            Log::warning("CURl -> RETRY: $retry ERROR: {$e->getMessage()} ERRNO: {$e->getCode()} STATUS:  Waiting for recovery!");
            sleep(15);
        }
        Env::failExit('网络异常，超出最大尝试次数，退出程序~');
    }

    /**
     * @use 获取请求配置
     * @param array $add_options
     * @param array $headers
     * @param float $timeout
     * @return array
     */
    private static function getClientOpt(array $add_options, array $headers = [], float $timeout = 30.0): array
    {
        self::$client = new Client();
        $default_options = [
            'headers' => $headers,
            'timeout' => $timeout,
            'http_errors' => false,
            'verify' => getConf('verify_ssl', 'network.ssl'),
        ];
        if (getConf('enable', 'network.proxy')) {
            $default_options['proxy'] = getConf('proxy', 'network.proxy');
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
        if (!self::$buvid) {
            // 缓存开始 如果存在就赋值 否则默认值
            if ($temp = getCache('buvid')) {
                self::$buvid = $temp;
            } else {
                self::$buvid = Generator::buvid();
            }
            // 缓存结束 需要的数据的放进缓存
            setCache('buvid', self::$buvid);
        }

        $app_headers = [
            'env' => 'prod',
            'APP-KEY' => 'android',
            'Buvid' => self::$buvid,
            'Accept' => '*/*',
            'Accept-Encoding' => 'gzip',
            'Accept-Language' => 'zh-cn',
            'Connection' => 'keep-alive',
            'User-Agent' => getDevice('device.app_headers.ua'),
            // 'Content-Type' => 'application/x-www-form-urlencoded',
            // 'User-Agent' => 'Mozilla/5.0 BiliDroid/5.51.1 (bbcallen@gmail.com)',
            // 'Referer' => 'https://live.bilibili.com/',
        ];
        $pc_headers = [
            'Accept' => "application/json, text/plain, */*",
            'Accept-Encoding' => 'gzip, deflate',
            'Accept-Language' => "zh-CN,zh;q=0.9",
            'User-Agent' => getDevice('device.pc_headers.ua'),
            // 'Content-Type' => 'application/x-www-form-urlencoded',
            // 'Referer' => 'https://live.bilibili.com/',
        ];
        $other_headers = [
            'User-Agent' => getDevice('device.other_headers.ua')
        ];
        $default_headers = ${$os . "_headers"} ?? $other_headers;
        if (in_array($os, ['app', 'pc']) && getCookie() != "") {
            $default_headers['Cookie'] = getCookie();
        }
        // return self::formatHeaders(array_merge($default_headers, $headers));
        return array_merge($default_headers, $headers);
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
     * @return string
     */
    private static function arr2str(array $array): string
    {
        $tmp = '';
        foreach ($array as $key => $value) {
            $tmp .= "$key:$value\r\n";
        }
        return $tmp;
    }

    /**
     * @use GET请求
     * @param $os
     * @param $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return mixed
     */
    public static function headers($os, $url, array $params = [], array $headers = [], float $timeout = 30.0): mixed
    {
        Log::debug('HEADERS: ' . $url);
        $headers = self::getHeaders($os, $headers);
        $payload['query'] = count($params) ? $params : [];
        $payload['allow_redirects'] = false;
        $options = self::getClientOpt($payload, $headers, $timeout);
        $request = self::clientHandle($url, 'get', $options);
        Log::debug("获取Headers");
        return $request->getHeaders();
    }

    /**
     * @use 格式化Headers
     * @param array $headers
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
    private static function getResult(): array
    {
        $result = self::$result;
        self::$result = [];
        return array_shift($result);
    }
}
