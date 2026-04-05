<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
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
use Bhp\Http\HttpClient;
use Bhp\Http\HttpResponse;
use Bhp\Http\RequestOptions;
use Bhp\Log\Log;
use Bhp\Runtime\AppContext;
use Bhp\Util\Exceptions\MethodNotFoundException;
use Bhp\Util\Exceptions\RequestException;
use Bhp\Util\Exceptions\ResponseEmptyException;
use Bhp\Util\Fake\Fake;
use Exception;
use RuntimeException;
use Throwable;

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
class Request
{
    private static ?self $current = null;

    /**
     * @var array
     */
    protected array $caches = [];

    /**
     * @var array
     */
    protected array $retry_attempts;

    /**
     * @var float
     */
    protected float $timeout;

    /**
     * @var string|null
     */
    protected ?string $buvid = null;

    /**
     * @param HttpClient $httpClient
     * @param AppContext $context
     * @param int $min_rt
     * @param int $max_rt
     * @param float $timeout
     * @return void
     */
    public function __construct(
        protected readonly HttpClient $httpClient,
        protected readonly AppContext $context,
        protected readonly Cache $cache,
        int $min_rt = 0,
        int $max_rt = 40,
        float $timeout = 30.0,
    ) {
        self::$current = $this;
        $this->cache->initializeScope();
        $this->retry_attempts = range($min_rt, $max_rt);
        $this->timeout = $timeout;
    }

    /**
     * @return string
     */
    public static function getBuvid(): string
    {
        return (string)(self::service()->buvid ?? '');
    }

    public function buvidValue(): string
    {
        if ($this->buvid === null) {
            $this->buvid = ($tmp = $this->cache->pull('buvid')) ? $tmp : Fake::buvid();
            $this->cache->put('buvid', $this->buvid);
        }

        return (string)$this->buvid;
    }

    public static function auth(string $key): string
    {
        return self::service()->context->auth($key);
    }

    public static function csrf(): string
    {
        return self::service()->context->csrf();
    }

    public function csrfValue(): string
    {
        return $this->context->csrf();
    }

    public static function uid(): string
    {
        return self::service()->context->uid();
    }

    public function uidValue(): string
    {
        return $this->context->uid();
    }

    public static function sid(): string
    {
        return self::service()->context->sid();
    }

    public function sidValue(): string
    {
        return $this->context->sid();
    }

    public static function config(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return self::service()->context->config($key, $default, $type);
    }

    public static function device(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return self::service()->context->device($key, $default, $type);
    }

    public static function signAndroid(array $payload): array
    {
        return self::service()->signAndroidPayload($payload);
    }

    public static function signLogin(array $payload): array
    {
        return self::service()->signLoginPayload($payload);
    }

    public static function signTv(array $payload): array
    {
        return self::service()->signTvPayload($payload);
    }

    public static function signCommon(array $payload, bool $includeStatistics = false): array
    {
        return self::service()->signCommonPayload($payload, $includeStatistics);
    }

    public function signAndroidPayload(array $payload): array
    {
        $appKey = base64_decode((string)$this->context->device('app.bili_a.app_key_n'));
        $appSecret = base64_decode((string)$this->context->device('app.bili_a.secret_key_n'));
        $default = [
            'access_key' => $this->context->auth('access_token'),
            'actionKey' => 'appkey',
            'appkey' => $appKey,
            'build' => $this->context->device('app.bili_a.build'),
            'channel' => $this->context->device('app.bili_a.channel'),
            'device' => $this->context->device('app.bili_a.device'),
            'mobi_app' => $this->context->device('app.bili_a.mobi_app'),
            'platform' => $this->context->device('app.bili_a.platform'),
            'c_locale' => 'zh_CN',
            's_locale' => 'zh_CN',
            'disable_rcmd' => '0',
            'ts' => time(),
        ];

        return self::signPayload(array_merge($payload, $default), (string)$appSecret);
    }

    public function signLoginPayload(array $payload): array
    {
        return match ((int)$this->context->config('login_mode.mode')) {
            2, 1 => $this->signAndroidPayload($payload),
            3 => $this->signTvPayload($payload),
            default => $this->signCommonPayload($payload),
        };
    }

    public function signTvPayload(array $payload): array
    {
        $appKey = base64_decode((string)$this->context->device('app.bili_t.app_key'));
        $appSecret = base64_decode((string)$this->context->device('app.bili_t.secret_key'));
        $default = [
            'appkey' => $appKey,
            'local_id' => 0,
            'ts' => time(),
        ];

        return self::signPayload(array_merge($payload, $default), (string)$appSecret);
    }

    public function signCommonPayload(array $payload, bool $includeStatistics = false): array
    {
        $appKey = base64_decode((string)$this->context->device('app.bili_a.app_key'));
        $appSecret = base64_decode((string)$this->context->device('app.bili_a.secret_key'));
        $default = [
            'access_key' => $this->context->auth('access_token'),
            'actionKey' => 'appkey',
            'appkey' => $appKey,
            'build' => $this->context->device('app.bili_a.build'),
            'device' => $this->context->device('app.bili_a.device'),
            'mobi_app' => $this->context->device('app.bili_a.mobi_app'),
            'platform' => $this->context->device('app.bili_a.platform'),
            'c_locale' => 'zh_CN',
            's_locale' => 'zh_CN',
            'disable_rcmd' => '0',
            'ts' => time(),
        ];
        if ($includeStatistics) {
            $default['statistics'] = $this->context->device('app.bili_a.statistics');
        }

        return self::signPayload(array_merge($payload, $default), (string)$appSecret);
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
            $this->buvid = ($tmp = $this->cache->pull('buvid')) ? $tmp : Fake::buvid();
            $this->cache->put('buvid', $this->buvid);
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
            'User-Agent' => (string)$this->context()->device('platform.headers.app_ua'),
            // 'Content-Type' => 'application/x-www-form-urlencoded',
            // 'User-Agent' => 'Mozilla/5.0 BiliDroid/5.51.1 (bbcallen@gmail.com)',
            // 'Referer' => 'https://live.bilibili.com/',
        ];
        //
        $pc_headers = [
            'Accept' => "application/json, text/plain, */*",
            'Accept-Encoding' => 'gzip, deflate',
            'Accept-Language' => "zh-CN,zh;q=0.9",
            'User-Agent' => (string)$this->context()->device('platform.headers.pc_ua'),
            // 'Content-Type' => 'application/x-www-form-urlencoded',
            // 'Referer' => 'https://live.bilibili.com/',
        ];
        //
        $other_headers = [
            'User-Agent' => (string)$this->context()->device('platform.headers.other_ua'),
        ];
        $default_headers = ${$os . "_headers"} ?? $other_headers;
        $cookie = $this->context()->auth('cookie');
        if (in_array($os, ['app', 'pc']) && $cookie !== '') {
            // patch cookie
            $pcCookie = $this->context()->auth('pc_cookie');
            if ($os == 'pc' && $pcCookie !== '') {
                $default_headers['Cookie'] = $pcCookie;
            } else {
                $default_headers['Cookie'] = $cookie;
            }
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
            'verify' => $this->context()->config('network_ssl.verify', false, 'bool'),
        ];
        if ($this->context()->enabled('network_proxy')) {
            $default_options['proxy'] = (string)$this->context()->config('network_proxy.proxy');
        }
        //
        $this->setRequest($request_id, 'options', array_merge($default_options, $add_options));
        return $this;

    }

    /**
     * 处理请求
     * @param string $request_id
     * @return HttpResponse
     */
    protected function handle(string $request_id): HttpResponse
    {
        return $this->handleWithHttpClient($request_id);
    }

    protected function handleWithHttpClient(string $request_id): HttpResponse
    {
        $url = (string)$this->getRequest($request_id, 'url');
        $method = (string)$this->getRequest($request_id, 'method');
        $options = (array)$this->getRequest($request_id, 'options');
        $requestOptions = $this->toHttpRequestOptions($options);
        $classifier = new RequestFailureClassifier();
        $lastException = null;
        $lastCategory = RequestException::CATEGORY_UNKNOWN;
        $retryCount = 0;

        foreach ($this->retry_attempts as $retry) {
            $retryCount++;
            try {
                $response = $this->httpClient->send($method, $url, $requestOptions);
                if ($response->getBody() === '' && $requestOptions->sink === null) {
                    throw new ResponseEmptyException('Value IsEmpty');
                }

                return $response;
            } catch (ResponseEmptyException|Exception $e) {
                $lastException = $e;
                $lastCategory = $classifier->classify($e);
                $this->context->log()->recordWarning("Target -> URL: {$url} METHOD: {$method}");
                $this->context->log()->recordWarning("HTTP -> RETRY: {$retry} CATEGORY: {$lastCategory} ERROR: {$e->getMessage()} ERRNO: {$e->getCode()} STATUS: Waiting for recovery!");
            } catch (Throwable $throwable) {
                $lastException = new RuntimeException($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
                $lastCategory = $classifier->classifyMessage($throwable->getMessage());
                $this->context->log()->recordWarning("Target -> URL: {$url} METHOD: {$method}");
                $this->context->log()->recordWarning("HTTP -> RETRY: {$retry} CATEGORY: {$lastCategory} ERROR: {$lastException->getMessage()} ERRNO: {$lastException->getCode()} STATUS: Waiting for recovery!");
            }
        }

        $requestExceptionMessage = $lastException instanceof Exception
            ? sprintf('网络异常，重试 %d 次后仍失败: %s', $retryCount, $lastException->getMessage())
            : sprintf('网络异常，重试 %d 次后仍失败', $retryCount);
        $requestExceptionId = $lastException instanceof RequestException && $lastException->getRequestId() !== ''
            ? $lastException->getRequestId()
            : $request_id;

        throw new RequestException(
            $requestExceptionMessage,
            $method,
            $url,
            (int)($lastException?->getCode() ?? 0),
            $lastException,
            $lastCategory,
            $requestExceptionId,
            $retryCount,
        );
    }

    /**
     * Method GetResponse
     * @param string $os
     * @param string $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return HttpResponse
     */
    protected static function _getResponse(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): HttpResponse
    {
        return self::service()->getResponseInstance($os, $url, $params, $headers, $timeout);
    }

    /**
     * Method Get
     * @param mixed ...$params
     * @return string
     */
    protected static function _get(mixed ...$params): string
    {
        return self::service()->getText(...$params);
    }

    /**
     * Method PostResponse
     * @param $os
     * @param $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return HttpResponse
     */
    protected static function _postResponse($os, $url, array $params = [], array $headers = [], float $timeout = 30.0): HttpResponse
    {
        return self::service()->postResponseInstance($os, $url, $params, $headers, $timeout);
    }

    /**
     * Method Post
     * @param mixed ...$params
     * @return string
     */
    protected static function _post(mixed ...$params): string
    {
        return self::service()->postText(...$params);
    }

    /**
     * POST JSON Body
     * @param string $os
     * @param string $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return string
     */
    protected static function _postJsonBody(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return self::service()->postJsonBodyText($os, $url, $params, $headers, $timeout);
    }

    /**
     * Method PutResponse
     * @param $os
     * @param $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return HttpResponse
     */
    protected static function _putResponse($os, $url, array $params = [], array $headers = [], float $timeout = 30.0): HttpResponse
    {
        return self::service()->putResponseInstance($os, $url, $params, $headers, $timeout);
    }

    /**
     * Method Put
     * @param mixed ...$params
     * @return string
     */
    protected static function _put(mixed ...$params): string
    {
        return self::service()->putText(...$params);
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
     * @param bool $quiet
     * @return bool|string|null
     */
    public static function single(string $method, string $url, array $payload = [], array $headers = [], int $timeout = 10, bool $quiet = false): bool|string|null
    {
        return self::service()->sendSingle($method, $url, $payload, $headers, $timeout, $quiet);
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
        if (!isset($this->caches[$request_id]) || !array_key_exists($key, $this->caches[$request_id])) {
            throw new RuntimeException("Missing request cache entry {$request_id}.{$key}");
        }

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
        return self::service()->fetchHeaders($os, $url, $params, $headers, $timeout);
    }

    public function getResponseInstance(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): HttpResponse
    {
        $rid = $this->startRequest();
        $this->context->log()->recordDebug("[GET#$rid] $url ", $params);
        $payload['query'] = count($params) ? $params : [];
        try {
            $response = $this
                ->withUrl($rid, $url)
                ->withMethod($rid, 'get')
                ->withHeaders($rid, $os, $headers)
                ->withOptions($rid, $payload, $timeout)
                ->handle($rid);

            $this->context->log()->recordDebug("[GET#$rid] " . $response->getBody());

            return $response;
        } finally {
            $this->stopRequest($rid);
        }
    }

    public function getText(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return (string)$this->getResponseInstance($os, $url, $params, $headers, $timeout)->getBody();
    }

    public function postResponseInstance(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): HttpResponse
    {
        $rid = $this->startRequest();
        $this->context->log()->recordDebug("[POST#$rid] $url ", $params);
        $payload['form_params'] = count($params) ? $params : [];
        try {
            $response = $this
                ->withUrl($rid, $url)
                ->withMethod($rid, 'post')
                ->withHeaders($rid, $os, $headers)
                ->withOptions($rid, $payload, $timeout)
                ->handle($rid);

            $this->context->log()->recordDebug("[POST#$rid] " . $response->getBody());

            return $response;
        } finally {
            $this->stopRequest($rid);
        }
    }

    public function postText(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return (string)$this->postResponseInstance($os, $url, $params, $headers, $timeout)->getBody();
    }

    public function postJsonBodyText(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        $rid = $this->startRequest();
        $this->context->log()->recordDebug("[POST_JSON#$rid] $url ", $params);
        $payload['json'] = count($params) ? $params : [];
        try {
            $response = $this
                ->withUrl($rid, $url)
                ->withMethod($rid, 'post')
                ->withHeaders($rid, $os, $headers)
                ->withOptions($rid, $payload, $timeout)
                ->handle($rid);

            $this->context->log()->recordDebug("[POST_JSON#$rid] " . $response->getBody());

            return (string)$response->getBody();
        } finally {
            $this->stopRequest($rid);
        }
    }

    public function putResponseInstance(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): HttpResponse
    {
        $rid = $this->startRequest();
        $this->context->log()->recordDebug("[PUT#$rid] $url ", $params);
        $payload['json'] = count($params) ? $params : [];
        try {
            $response = $this
                ->withUrl($rid, $url)
                ->withMethod($rid, 'put')
                ->withHeaders($rid, $os, $headers)
                ->withOptions($rid, $payload, $timeout)
                ->handle($rid);

            $this->context->log()->recordDebug("[PUT#$rid] " . $response->getBody());

            return $response;
        } finally {
            $this->stopRequest($rid);
        }
    }

    public function putText(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return (string)$this->putResponseInstance($os, $url, $params, $headers, $timeout)->getBody();
    }

    public function sendSingle(string $method, string $url, array $payload = [], array $headers = [], int $timeout = 10, bool $quiet = false): bool|string|null
    {
        $this->context->log()->recordDebug("[SINGLE] $url ", $payload);
        if ($url === '') {
            return null;
        }

        $options = new RequestOptions();
        $options->headers = $headers;
        $options->timeout = (float)$timeout;
        $options->quiet = $quiet;

        $normalizedMethod = strtolower($method);
        if ($normalizedMethod === 'get') {
            $options->query = $payload;
        } else {
            $options->formParams = $payload;
        }

        try {
            $response = $this->httpClient->send($method, $url, $options);
            $result = $response->getBody();
            $this->context->log()->recordDebug("[SINGLE] $result");

            return $result !== '' ? $result : null;
        } catch (Throwable $throwable) {
            if ($quiet) {
                $this->context->log()->recordDebug("[SINGLE] {$throwable->getMessage()}");
            } else {
                $this->context->log()->recordWarning("[SINGLE] {$throwable->getMessage()}");
            }

            return null;
        }
    }

    public function fetchHeaders(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): array
    {
        $rid = $this->startRequest();
        $this->context->log()->recordDebug("[HEADERS#$rid] $url ", $params);
        $payload['query'] = count($params) ? $params : [];
        $payload['allow_redirects'] = false;
        try {
            $response = $this
                ->withUrl($rid, $url)
                ->withMethod($rid, 'get')
                ->withHeaders($rid, $os, $headers)
                ->withOptions($rid, $payload, $timeout)
                ->handle($rid);

            $this->context->log()->recordDebug("[HEADERS#$rid] " . $response->getBody());

            return $response->getHeaders();
        } finally {
            $this->stopRequest($rid);
        }
    }

    /**
     * @return array<string, string>
     */
    public function diagnosticsSummary(): array
    {
        $retryAttempts = count($this->retry_attempts);
        $firstRetry = $retryAttempts > 0 ? (string)$this->retry_attempts[0] : '0';
        $lastRetry = $retryAttempts > 0 ? (string)$this->retry_attempts[$retryAttempts - 1] : '0';
        $retrySequence = $retryAttempts > 1 ? $firstRetry . '..' . $lastRetry : $lastRetry;
        $buvidCached = $this->buvid !== null || $this->cache->pull('buvid') !== false;

        return [
            'timeout_seconds' => (string)$this->timeout,
            'retry_attempts' => (string)$retryAttempts,
            'retry_sequence' => $retrySequence,
            'retry_last' => $lastRetry,
            'proxy_enabled' => $this->context()->enabled('network_proxy') ? 'yes' : 'no',
            'buvid_cached' => $buvidCached ? 'yes' : 'no',
            'governance_enabled' => $this->context()->config('request_governance.enable', false, 'bool') ? 'yes' : 'no',
            'governance_mode' => (string)$this->context()->config('request_governance.mode', 'observe'),
            'governance_window_seconds' => (string)$this->context()->config('request_governance.window_seconds', 60, 'int'),
            'governance_max_requests_per_host' => (string)$this->context()->config('request_governance.max_requests_per_host', 60, 'int'),
            'governance_cooldown_seconds' => (string)$this->context()->config('request_governance.cooldown_seconds', 30, 'int'),
        ];
    }

    protected static function signPayload(array $payload, string $appSecret): array
    {
        if (isset($payload['sign'])) {
            unset($payload['sign']);
        }
        ksort($payload);
        $data = http_build_query($payload);
        $payload['sign'] = md5($data . $appSecret);

        return $payload;
    }

    protected function toHttpRequestOptions(array $options): RequestOptions
    {
        $requestOptions = new RequestOptions();
        $requestOptions->headers = (array)($options['headers'] ?? []);
        $requestOptions->query = (array)($options['query'] ?? []);
        $requestOptions->json = isset($options['json']) && is_array($options['json']) ? $options['json'] : null;
        $requestOptions->formParams = isset($options['form_params']) && is_array($options['form_params']) ? $options['form_params'] : null;
        $requestOptions->body = isset($options['body']) && is_string($options['body']) ? $options['body'] : null;
        $requestOptions->sink = isset($options['sink']) && is_string($options['sink']) ? $options['sink'] : null;
        $requestOptions->followRedirects = (bool)($options['allow_redirects'] ?? true);
        $requestOptions->timeout = (float)($options['timeout'] ?? $this->timeout);
        $requestOptions->proxy = isset($options['proxy']) && is_string($options['proxy']) ? $options['proxy'] : null;

        return $requestOptions;
    }

    protected function context(): AppContext
    {
        return $this->context;
    }

    private static function service(): self
    {
        if (self::$current instanceof self) {
            return self::$current;
        }

        throw new RuntimeException('Request has not been bootstrapped.');
    }

}
