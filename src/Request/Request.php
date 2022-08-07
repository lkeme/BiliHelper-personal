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

namespace Bhp\Request;

use Bhp\Cache\Cache;
use Bhp\Log\Log;
use Bhp\Util\ArrayR\ArrayR;
use Bhp\Util\DesignPattern\SingleTon;
use Bhp\Util\Exceptions\MethodNotFoundException;
use Bhp\Util\Exceptions\ResponseEmptyException;
use Bhp\Util\Fake\Fake;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * @method static get(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0)
 * @method static getResponse(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0)
 * @method static getJson(?bool $associative, string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0)
 * @method static post(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0)
 * @method static postResponse(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0)
 * @method static postJson(?bool $associative, string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0)
 * @method static put(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0)
 * @method static putResponse(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0)
 * @method static putJson(?bool $associative, string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0)
 */
class Request extends SingleTon
{
    /**
     * @var array
     */
    protected array $caches;

    /**
     * @var Client|null
     */
    protected ?Client $client = null;

    /**
     * @var array
     */
    protected array $retry_times;

    /**
     * @var float
     */
    protected float $timeout;

    /**
     * @var string|null
     */
    protected ?string $buvid = null;

    /**
     * @param int $min_rt
     * @param int $max_rt
     * @param float $timeout
     * @return void
     */
    public function init(int $min_rt = 0, int $max_rt = 40, float $timeout = 30.0): void
    {
        Cache::initCache();
        $this->retry_times = range($min_rt, $max_rt);
        $this->timeout = $timeout;
    }

    /**
     * @return string
     */
    public static function getBuvid(): string
    {
        return self::getInstance()->buvid;
    }


    /**
     * 初始化客户端
     * @param string $request_id
     * @param array $config
     * @return Request
     */
    protected function withClient(string $request_id, array $config = []): static
    {
        if (is_null($this->client)) {
            $this->client = new Client($config);
        }
        // clone 对象深拷贝
        $this->setRequest($request_id, 'client', clone $this->client);
        return $this;
    }

    /**
     * 请求地址
     * @param string $request_id
     * @param string $url
     * @return $this
     */
    protected function withUrl(string $request_id, string $url): static
    {
        $this->setRequest($request_id, 'url', $url);
        return $this;
    }

    /**
     * 请求方式
     * @param string $request_id
     * @param string $method
     * @return $this
     */
    protected function withMethod(string $request_id, string $method): static
    {
        $this->setRequest($request_id, 'method', $method);
        return $this;
    }

    /**
     * 请求头
     * @param string $request_id
     * @param string $os
     * @param array $headers
     * @return $this
     */
    protected function withHeaders(string $request_id, string $os = 'app', array $headers = []): static
    {
        // 从缓存中加载 如果存在就赋值 否则默认值
        if (is_null($this->buvid)) {
            $this->buvid = ($tmp = Cache::get('buvid')) ? $tmp : Fake::buvid();
            Cache::set('buvid', $this->buvid);
        }
        //
        $app_headers = [
            'env' => 'prod',
            'APP-KEY' => 'android',
            'Buvid' => $this->buvid,
            'Accept' => '*/*',
            'Accept-Encoding' => 'gzip',
            'Accept-Language' => 'zh-cn',
            'Connection' => 'keep-alive',
            'User-Agent' => getDevice('platform.headers.app_ua'),
            // 'Content-Type' => 'application/x-www-form-urlencoded',
            // 'User-Agent' => 'Mozilla/5.0 BiliDroid/5.51.1 (bbcallen@gmail.com)',
            // 'Referer' => 'https://live.bilibili.com/',
        ];
        //
        $pc_headers = [
            'Accept' => "application/json, text/plain, */*",
            'Accept-Encoding' => 'gzip, deflate',
            'Accept-Language' => "zh-CN,zh;q=0.9",
            'User-Agent' => getDevice('platform.headers.pc_ua'),
            // 'Content-Type' => 'application/x-www-form-urlencoded',
            // 'Referer' => 'https://live.bilibili.com/',
        ];
        //
        $other_headers = [
            'User-Agent' => getDevice('platform.headers.other_ua'),
        ];
        $default_headers = ${$os . "_headers"} ?? $other_headers;
        if (in_array($os, ['app', 'pc']) && getU('cookie') != "") {
            $default_headers['Cookie'] = getU('cookie');
        }
        //
        $this->setRequest($request_id, 'headers', array_merge($default_headers, $headers));
        return $this;
    }

    /**
     * 客户端配置
     * @param string $request_id
     * @param array $add_options
     * @param float $timeout
     * @return Request
     */
    protected function withOptions(string $request_id, array $add_options, float $timeout = 30.0): static
    {
        $default_options = [
            // 'connect_timeout' => 10,
            // 'debug' => false,
            'headers' => $this->getRequest($request_id, 'headers'),
            'timeout' => $timeout,
            'http_errors' => false,
            'verify' => getConf('network_ssl.verify', false, 'bool'),
        ];
        if (getEnable('network_proxy')) {
            $default_options['proxy'] = getConf('network_proxy.proxy');
        }
        //
        $this->setRequest($request_id, 'options', array_merge($default_options, $add_options));
        return $this;

    }

    /**
     * 处理请求
     * @param string $request_id
     * @return ResponseInterface
     */
    protected function handle(string $request_id): ResponseInterface
    {
        $client = (object)$this->getRequest($request_id, 'client');
        $url = (string)$this->getRequest($request_id, 'url');
        $method = (string)$this->getRequest($request_id, 'method');
        $options = (array)$this->getRequest($request_id, 'options');
        //
        foreach ($this->retry_times as $retry) {
            try {
                // $response = $this->client->get($url, $options);
                // $response = $this->client->$method($url, $options);
                // 稍有性能损耗，不过影响不大
                $response = call_user_func_array([$client, $method], [$url, $options]);
                if (is_null($response) or empty($response)) throw new ResponseEmptyException("Value IsEmpty");
                return $response;
            } catch (RequestException $e) {
                // var_dump($e->getRequest());
                if ($e->hasResponse()) var_dump($e->getResponse());
            } catch (ResponseEmptyException|Exception $e) {
                // $e->getHandlerContext()
                // var_dump($e);
            }
            Log::warning("Target -> URL: $url METHOD: $method");
            Log::warning("CURl -> RETRY: $retry ERROR: {$e->getMessage()} ERRNO: {$e->getCode()} STATUS:  Waiting for recovery!");
            sleep(15);
        }
        failExit('网络异常，超出最大尝试次数，退出程序~');
    }

    /**
     * Method GetResponse
     * @param string $os
     * @param string $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return ResponseInterface
     */
    protected static function _getResponse(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): ResponseInterface
    {
        //
        $rid = self::getInstance()->startRequest();
        //
        Log::debug("[GET#$rid] $url ", $params);
        //
        $payload['query'] = count($params) ? $params : [];
        //
        $response = self::getInstance()
            ->withClient($rid)
            ->withUrl($rid, $url)
            ->withMethod($rid, 'get')
            ->withHeaders($rid, $os, $headers)
            ->withOptions($rid, $payload, $timeout)
            ->handle($rid);
        //
        self::getInstance()->stopRequest($rid);
        //
        Log::debug("[GET#$rid] " . $response->getBody());
        //
        return $response;
    }

    /**
     * Method Get
     * @param mixed ...$params
     * @return string
     */
    protected static function _get(mixed ...$params): string
    {
        $response = self::getResponse(...$params);
        return (string)$response->getBody();
    }

    /**
     * Method GetJson
     * @param bool|null $associative
     * @param mixed ...$params
     * @return mixed
     */
    protected static function _getJson(?bool $associative = null, mixed...$params): mixed
    {
        $response = self::get(...$params);
        // JSON_UNESCAPED_UNICODE 当该参数为 TRUE 时，将返回 array 而非 object 。
        return json_decode($response, $associative);
    }

    /**
     * Method PostResponse
     * @param $os
     * @param $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return ResponseInterface
     */
    protected static function _postResponse($os, $url, array $params = [], array $headers = [], float $timeout = 30.0): ResponseInterface
    {
        //
        $rid = self::getInstance()->startRequest();
        //
        Log::debug("[POST#$rid] $url ", $params);
        //
        $payload['form_params'] = count($params) ? $params : [];
        //
        $response = self::getInstance()
            ->withClient($rid)
            ->withUrl($rid, $url)
            ->withMethod($rid, 'post')
            ->withHeaders($rid, $os, $headers)
            ->withOptions($rid, $payload, $timeout)
            ->handle($rid);
        //
        self::getInstance()->stopRequest($rid);
        //
        Log::debug("[POST#$rid] " . $response->getBody());
        //
        return $response;
    }

    /**
     * Method Post
     * @param mixed ...$params
     * @return string
     */
    protected static function _post(mixed ...$params): string
    {
        $response = self::postResponse(...$params);
        return (string)$response->getBody();
    }

    /**
     * Method PostJson
     * @param bool|null $associative
     * @param mixed ...$params
     * @return mixed
     */
    protected static function _postJson(?bool $associative = null, mixed...$params): mixed
    {
        $response = self::post(...$params);
        // JSON_UNESCAPED_UNICODE
        return json_decode($response, $associative);
    }

    /**
     * Method PutResponse
     * @param $os
     * @param $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return ResponseInterface
     */
    protected static function _putResponse($os, $url, array $params = [], array $headers = [], float $timeout = 30.0): ResponseInterface
    {
        //
        $rid = self::getInstance()->startRequest();
        //
        Log::debug("[PUT#$rid] $url ", $params);
        //
        $payload['json'] = count($params) ? $params : [];
        //
        $response = self::getInstance()
            ->withClient($rid)
            ->withUrl($rid, $url)
            ->withMethod($rid, 'post')
            ->withHeaders($rid, $os, $headers)
            ->withOptions($rid, $payload, $timeout)
            ->handle($rid);
        //
        self::getInstance()->stopRequest($rid);
        //
        Log::debug("[PUT#$rid] " . $response->getBody());
        //
        return $response;
    }

    /**
     * Method Put
     * @param mixed ...$params
     * @return string
     */
    protected static function _put(mixed ...$params): string
    {
        $response = self::putResponse(...$params);
        return (string)$response->getBody();
    }

    /**
     * Method PutJson
     * @param bool|null $associative
     * @param mixed ...$params
     * @return mixed
     */
    protected static function __putJson(?bool $associative = null, mixed...$params): mixed
    {
        $response = self::put(...$params);
        // JSON_UNESCAPED_UNICODE
        return json_decode($response, $associative);
    }


    /**
     * 下载文件
     * @param $os
     * @param $url
     * @param string $filepath
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return mixed
     */
    public static function download($os, $url, string $filepath, array $params = [], array $headers = [], float $timeout = 600.0): string
    {
        $params = array_merge($params, [
            'sink' => $filepath,
        ]);
        return self::get($os, $url, $params, $headers, $timeout);
    }

    /**
     * 单次请求
     * @param string $method
     * @param string $url
     * @param array $payload
     * @param array $headers
     * @param int $timeout
     * @return bool|string|null
     */
    public static function single(string $method, string $url, array $payload = [], array $headers = [], int $timeout = 10): bool|string|null
    {
        Log::debug("[SINGLE] $url ", $payload);
        //
        $options = array(
            'http' => array(
                'method' => strtoupper($method),
                'header' => ArrayR::toStr($headers),
                'content' => http_build_query($payload),
                'timeout' => $timeout,
            ),
        );
        $result = $url ? @file_get_contents($url, false, stream_context_create($options)) : null;
        //
        Log::debug("[SINGLE] $result");
        return $result ?: null;
    }

    // postAsync  getAsync
    protected static function multiple(string $os, array $tasks = [], float $timeout = 30.0)
    {


    }


    public function standardizeParam($param): array
    {
        return array_map(function ($item) {
            if (is_array($item)) {
                return $this->standardizeParam($item);
            } else {
                return (string)$item;
            }
        }, $param);
    }


    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws MethodNotFoundException
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        $protectedName = '_' . $name;
        if (method_exists(self::class, $protectedName)) {
            return self::$protectedName(...$arguments);
        }
        throw new MethodNotFoundException('Call undefined method ' . self::class . ':' . $name . '()');
    }

    /**
     * 开始请求
     * @return string
     */
    protected function startRequest(): string
    {
        $request_id = Fake::hash();
        $this->caches[$request_id] = [];
        return $request_id;
    }

    /**
     * @param string $request_id
     * @param string $key
     * @param mixed $values
     * @return void
     */
    protected function setRequest(string $request_id, string $key, mixed $values): void
    {
        $this->caches[$request_id][$key] = $values;
    }

    /**
     * @param string $request_id
     * @param string $key
     * @return mixed
     */
    protected function getRequest(string $request_id, string $key): mixed
    {
        return $this->caches[$request_id][$key];
    }

    /**
     * 结束请求
     * @param string $request_id
     * @return void
     */
    protected function stopRequest(string $request_id): void
    {
        unset($this->caches[$request_id]);
    }

    /**
     * GET请求
     * @param $os
     * @param $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return mixed
     */
    public static function headers($os, $url, array $params = [], array $headers = [], float $timeout = 30.0): array
    {
        //
        $rid = self::getInstance()->startRequest();
        //
        Log::debug("[HEADERS#$rid] $url ", $params);
        //
        $payload['query'] = count($params) ? $params : [];
        $payload['allow_redirects'] = false;
        //
        $response = self::getInstance()
            ->withClient($rid)
            ->withUrl($rid, $url)
            ->withMethod($rid, 'get')
            ->withHeaders($rid, $os, $headers)
            ->withOptions($rid, $payload, $timeout)
            ->handle($rid);
        //
        self::getInstance()->stopRequest($rid);
        //
        Log::debug("[HEADERS#$rid] " . $response->getBody());
        //
        return $response->getHeaders();
    }

}