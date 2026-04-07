<?php declare(strict_types=1);

namespace Bhp\Request;

use Bhp\Cache\Cache;
use Bhp\Http\HttpClient;
use Bhp\Http\HttpResponse;
use Bhp\Http\RequestOptions;
use Bhp\Runtime\AppContext;
use Bhp\Util\Exceptions\RequestException;
use Bhp\Util\Exceptions\ResponseEmptyException;
use Bhp\Util\Fake\Fake;
use RuntimeException;
use Throwable;

class Request
{
    private const CACHE_SCOPE = 'Request';

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $caches = [];

    protected float $timeout;
    protected ?string $buvid = null;
    private readonly RequestRetryPolicy $retryPolicy;
    private readonly RequestFailureClassifier $failureClassifier;
    private readonly \Closure $sleep;

    public function __construct(
        protected readonly HttpClient $httpClient,
        protected readonly AppContext $context,
        protected readonly Cache $cache,
        ?RequestRetryPolicy $retryPolicy = null,
        ?callable $sleep = null,
        float $timeout = 30.0,
    ) {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $this->retryPolicy = $retryPolicy ?? new RequestRetryPolicy();
        $this->failureClassifier = new RequestFailureClassifier();
        $this->sleep = $sleep !== null
            ? \Closure::fromCallable($sleep)
            : static function (float $seconds): void {
                if ($seconds <= 0.0) {
                    return;
                }

                usleep((int)round($seconds * 1_000_000));
            };
        $this->timeout = $timeout;
    }

    public function buvidValue(): string
    {
        if ($this->buvid === null) {
            $cached = $this->cache->pull('buvid', self::CACHE_SCOPE);
            $this->buvid = is_string($cached) && $cached !== '' ? $cached : Fake::buvid();
            $this->cache->put('buvid', $this->buvid, self::CACHE_SCOPE);
        }

        return $this->buvid;
    }

    public function csrfValue(): string
    {
        return $this->context->csrf();
    }

    public function uidValue(): string
    {
        return $this->context->uid();
    }

    public function sidValue(): string
    {
        return $this->context->sid();
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
            1, 2 => $this->signAndroidPayload($payload),
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

    public function getResponseInstance(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): HttpResponse
    {
        $requestId = $this->startRequest();
        $this->context->log()->recordDebug("[GET#{$requestId}] {$url}", $params);

        try {
            $response = $this
                ->withUrl($requestId, $url)
                ->withMethod($requestId, 'get')
                ->withHeaders($requestId, $os, $headers)
                ->withOptions($requestId, ['query' => $params], $timeout)
                ->handle($requestId);

            $this->context->log()->recordDebug("[GET#{$requestId}] " . $response->getBody());

            return $response;
        } finally {
            $this->stopRequest($requestId);
        }
    }

    public function getText(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return $this->getResponseInstance($os, $url, $params, $headers, $timeout)->getBody();
    }

    public function postResponseInstance(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): HttpResponse
    {
        $requestId = $this->startRequest();
        $this->context->log()->recordDebug("[POST#{$requestId}] {$url}", $params);

        try {
            $response = $this
                ->withUrl($requestId, $url)
                ->withMethod($requestId, 'post')
                ->withHeaders($requestId, $os, $headers)
                ->withOptions($requestId, ['form_params' => $params], $timeout)
                ->handle($requestId);

            $this->context->log()->recordDebug("[POST#{$requestId}] " . $response->getBody());

            return $response;
        } finally {
            $this->stopRequest($requestId);
        }
    }

    public function postText(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return $this->postResponseInstance($os, $url, $params, $headers, $timeout)->getBody();
    }

    public function postTextWithQuery(
        string $os,
        string $url,
        array $formParams = [],
        array $query = [],
        array $headers = [],
        float $timeout = 30.0,
    ): string {
        $requestId = $this->startRequest();
        $this->context->log()->recordDebug("[POST_QUERY#{$requestId}] {$url}", [
            'query' => $query,
            'form' => $formParams,
        ]);

        try {
            $response = $this
                ->withUrl($requestId, $url)
                ->withMethod($requestId, 'post')
                ->withHeaders($requestId, $os, $headers)
                ->withOptions($requestId, [
                    'query' => $query,
                    'form_params' => $formParams,
                ], $timeout)
                ->handle($requestId);

            $this->context->log()->recordDebug("[POST_QUERY#{$requestId}] " . $response->getBody());

            return $response->getBody();
        } finally {
            $this->stopRequest($requestId);
        }
    }

    public function postJsonBodyText(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        $requestId = $this->startRequest();
        $this->context->log()->recordDebug("[POST_JSON#{$requestId}] {$url}", $params);

        try {
            $response = $this
                ->withUrl($requestId, $url)
                ->withMethod($requestId, 'post')
                ->withHeaders($requestId, $os, $headers)
                ->withOptions($requestId, ['json' => $params], $timeout)
                ->handle($requestId);

            $this->context->log()->recordDebug("[POST_JSON#{$requestId}] " . $response->getBody());

            return $response->getBody();
        } finally {
            $this->stopRequest($requestId);
        }
    }

    public function putResponseInstance(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): HttpResponse
    {
        $requestId = $this->startRequest();
        $this->context->log()->recordDebug("[PUT#{$requestId}] {$url}", $params);

        try {
            $response = $this
                ->withUrl($requestId, $url)
                ->withMethod($requestId, 'put')
                ->withHeaders($requestId, $os, $headers)
                ->withOptions($requestId, ['json' => $params], $timeout)
                ->handle($requestId);

            $this->context->log()->recordDebug("[PUT#{$requestId}] " . $response->getBody());

            return $response;
        } finally {
            $this->stopRequest($requestId);
        }
    }

    public function putText(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return $this->putResponseInstance($os, $url, $params, $headers, $timeout)->getBody();
    }

    public function sendSingle(string $method, string $url, array $payload = [], array $headers = [], int $timeout = 10, bool $quiet = false): bool|string|null
    {
        $this->context->log()->recordDebug("[SINGLE] {$url}", $payload);
        if ($url === '') {
            return null;
        }

        $options = new RequestOptions();
        $options->headers = $headers;
        $options->timeout = (float)$timeout;
        $options->quiet = $quiet;

        if (strtolower($method) === 'get') {
            $options->query = $payload;
        } else {
            $options->formParams = $payload;
        }

        try {
            $response = $this->httpClient->send($method, $url, $options);
            $result = $response->getBody();
            $this->context->log()->recordDebug("[SINGLE] {$result}");

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
        $requestId = $this->startRequest();
        $this->context->log()->recordDebug("[HEADERS#{$requestId}] {$url}", $params);

        try {
            $response = $this
                ->withUrl($requestId, $url)
                ->withMethod($requestId, 'get')
                ->withHeaders($requestId, $os, $headers)
                ->withOptions($requestId, [
                    'query' => $params,
                    'allow_redirects' => false,
                ], $timeout)
                ->handle($requestId);

            $this->context->log()->recordDebug("[HEADERS#{$requestId}] " . $response->getBody());

            return $response->getHeaders();
        } finally {
            $this->stopRequest($requestId);
        }
    }

    /**
     * @return array<string, string>
     */
    public function diagnosticsSummary(): array
    {
        $buvidCached = $this->buvid !== null || $this->cache->pull('buvid', self::CACHE_SCOPE) !== false;

        return [
            'timeout_seconds' => (string)$this->timeout,
            'retry_max_attempts' => (string)$this->retryPolicy->maxAttempts(),
            'retry_base_delay_seconds' => (string)$this->retryPolicy->baseDelaySeconds(),
            'retry_max_delay_seconds' => (string)$this->retryPolicy->maxDelaySeconds(),
            'retry_total_budget_seconds' => (string)$this->retryPolicy->totalBudgetSeconds(),
            'retry_jitter_ratio' => (string)$this->retryPolicy->jitterRatio(),
            'proxy_enabled' => $this->context()->enabled('network_proxy') ? 'yes' : 'no',
            'buvid_cached' => $buvidCached ? 'yes' : 'no',
            'governance_enabled' => $this->context()->config('request_governance.enable', false, 'bool') ? 'yes' : 'no',
            'governance_mode' => (string)$this->context()->config('request_governance.mode', 'observe'),
            'governance_window_seconds' => (string)$this->context()->config('request_governance.window_seconds', 60, 'int'),
            'governance_max_requests_per_host' => (string)$this->context()->config('request_governance.max_requests_per_host', 60, 'int'),
            'governance_cooldown_seconds' => (string)$this->context()->config('request_governance.cooldown_seconds', 30, 'int'),
        ];
    }

    protected function startRequest(): string
    {
        $requestId = Fake::hash();
        $this->caches[$requestId] = [];

        return $requestId;
    }

    protected function setRequest(string $requestId, string $key, mixed $values): void
    {
        $this->caches[$requestId][$key] = $values;
    }

    protected function getRequest(string $requestId, string $key): mixed
    {
        if (!isset($this->caches[$requestId]) || !array_key_exists($key, $this->caches[$requestId])) {
            throw new RuntimeException("Missing request cache entry {$requestId}.{$key}");
        }

        return $this->caches[$requestId][$key];
    }

    protected function stopRequest(string $requestId): void
    {
        unset($this->caches[$requestId]);
    }

    protected function withUrl(string $requestId, string $url): static
    {
        $this->setRequest($requestId, 'url', $url);

        return $this;
    }

    protected function withMethod(string $requestId, string $method): static
    {
        $this->setRequest($requestId, 'method', strtolower($method));

        return $this;
    }

    protected function withHeaders(string $requestId, string $os = 'app', array $headers = []): static
    {
        $this->setRequest($requestId, 'headers', array_merge($this->defaultHeadersForOs($os), $headers));

        return $this;
    }

    protected function withOptions(string $requestId, array $addOptions, float $timeout = 30.0): static
    {
        $defaultOptions = [
            'headers' => $this->getRequest($requestId, 'headers'),
            'timeout' => $timeout,
            'http_errors' => false,
            'verify' => $this->context()->config('network_ssl.verify', false, 'bool'),
        ];
        if ($this->context()->enabled('network_proxy')) {
            $defaultOptions['proxy'] = (string)$this->context()->config('network_proxy.proxy');
        }

        $this->setRequest($requestId, 'options', array_merge($defaultOptions, $addOptions));

        return $this;
    }

    protected function handle(string $requestId): HttpResponse
    {
        return $this->handleWithHttpClient($requestId);
    }

    protected function handleWithHttpClient(string $requestId): HttpResponse
    {
        $url = (string)$this->getRequest($requestId, 'url');
        $method = strtolower((string)$this->getRequest($requestId, 'method'));
        $options = (array)$this->getRequest($requestId, 'options');
        $requestOptions = $this->toHttpRequestOptions($options);
        $startedAt = microtime(true);
        $lastThrowable = null;
        $lastCategory = RequestException::CATEGORY_UNKNOWN;
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->httpClient->send($method, $url, $requestOptions);
                if ($response->getBody() === '' && $requestOptions->sink === null) {
                    throw new ResponseEmptyException('Value IsEmpty');
                }

                return $response;
            } catch (Throwable $throwable) {
                $lastThrowable = $throwable;
                $lastCategory = $this->failureClassifier->classifyThrowable($throwable);

                $elapsedSeconds = microtime(true) - $startedAt;
                if (!$this->retryPolicy->shouldRetry($method, $requestOptions, $lastCategory, $attempt, $elapsedSeconds)) {
                    break;
                }

                $delaySeconds = $this->retryPolicy->delaySecondsForAttempt($attempt);
                $this->context->log()->recordWarning(sprintf(
                    'HTTP -> ATTEMPT: %d METHOD: %s CATEGORY: %s URL: %s ERROR: %s NEXT_DELAY: %.3fs',
                    $attempt,
                    strtoupper($method),
                    $lastCategory,
                    $url,
                    $throwable->getMessage(),
                    $delaySeconds,
                ));
                $this->sleep($delaySeconds);
            }
        }

        $wrapped = $lastThrowable instanceof RuntimeException
            ? $lastThrowable
            : new RuntimeException(
                $lastThrowable?->getMessage() ?? 'unknown request error',
                (int)($lastThrowable?->getCode() ?? 0),
                $lastThrowable,
            );

        throw new RequestException(
            $wrapped->getMessage() !== ''
                ? sprintf('网络异常，重试 %d 次后仍失败: %s', $attempt, $wrapped->getMessage())
                : sprintf('网络异常，重试 %d 次后仍失败', $attempt),
            $method,
            $url,
            (int)$wrapped->getCode(),
            $wrapped,
            $lastCategory,
            $requestId,
            $attempt,
        );
    }

    protected static function signPayload(array $payload, string $appSecret): array
    {
        if (isset($payload['sign'])) {
            unset($payload['sign']);
        }

        ksort($payload);
        $payload['sign'] = md5(http_build_query($payload) . $appSecret);

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
        $requestOptions->retryable = (bool)($options['retryable'] ?? false);

        return $requestOptions;
    }

    protected function context(): AppContext
    {
        return $this->context;
    }

    /**
     * @return array<string, string>
     */
    private function defaultHeadersForOs(string $os): array
    {
        $cookie = $this->context()->auth('cookie');
        $appHeaders = [
            'env' => 'prod',
            'APP-KEY' => 'android',
            'Buvid' => $this->buvidValue(),
            'Accept' => '*/*',
            'Accept-Encoding' => 'gzip',
            'Accept-Language' => 'zh-cn',
            'Connection' => 'keep-alive',
            'User-Agent' => (string)$this->context()->device('platform.headers.app_ua'),
        ];
        $pcHeaders = [
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate',
            'Accept-Language' => 'zh-CN,zh;q=0.9',
            'User-Agent' => (string)$this->context()->device('platform.headers.pc_ua'),
        ];
        $otherHeaders = [
            'User-Agent' => (string)$this->context()->device('platform.headers.other_ua'),
        ];

        $defaultHeaders = match ($os) {
            'app' => $appHeaders,
            'pc' => $pcHeaders,
            default => $otherHeaders,
        };

        if (in_array($os, ['app', 'pc'], true) && $cookie !== '') {
            $pcCookie = $this->context()->auth('pc_cookie');
            $defaultHeaders['Cookie'] = $os === 'pc' && $pcCookie !== '' ? $pcCookie : $cookie;
        }

        return $defaultHeaders;
    }

    private function sleep(float $seconds): void
    {
        ($this->sleep)($seconds);
    }
}
