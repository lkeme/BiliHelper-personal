<?php declare(strict_types=1);

namespace Bhp\Http;

use Amp\CompositeCancellation;
use Amp\Future;
use Amp\ByteStream\Payload;
use Amp\Http\Client\Request as AmpRequest;
use Amp\TimeoutCancellation;
use Bhp\Log\Log;
use Bhp\Runtime\AppContext;
use Bhp\Util\Fake\Fake;
use function Amp\async;

class HttpClient
{
    private \Amp\Http\Client\HttpClient $client;
    private \Amp\Http\Client\HttpClient $noRedirectClient;

    /**
     * 初始化 HttpClient
     * @param AppContext $context
     * @param HttpClientInterceptorRegistry $interceptorRegistry
     */
    public function __construct(
        private readonly AppContext $context,
        private readonly HttpClientInterceptorRegistry $interceptorRegistry,
    ) {
        $verifyPeer = $this->context->config('network_ssl.verify', true, 'bool');
        $this->client = HttpClientFactory::create(true, (bool)$verifyPeer);
        $this->noRedirectClient = HttpClientFactory::create(false, (bool)$verifyPeer);
    }

    /**
     * 处理send
     * @param string $method
     * @param string $url
     * @param RequestOptions $options
     * @return HttpResponse
     */
    public function send(string $method, string $url, RequestOptions $options): HttpResponse
    {
        $context = $this->applyBeforeSendInterceptors(new HttpRequestContext(
            strtolower($method),
            $url,
            $options,
            Fake::hash(),
        ));

        $startedAt = hrtime(true);
        $request = new AmpRequest($this->appendQuery($context->url, $context->options->query), strtoupper($context->method));
        $request->setProtocolVersions(['1.1']);

        foreach ($context->options->headers as $name => $value) {
            $request->setHeader($name, (string)$value);
        }

        $request->setTcpConnectTimeout($context->options->timeout);
        $request->setTlsHandshakeTimeout($context->options->timeout);
        $request->setTransferTimeout($context->options->timeout);
        $request->setInactivityTimeout($context->options->timeout);

        if ($context->options->json !== null) {
            if (!$request->hasHeader('Content-Type')) {
                $request->setHeader('Content-Type', 'application/json');
            }
            $request->setBody((string)json_encode($context->options->json, JSON_UNESCAPED_UNICODE));
        } elseif ($context->options->formParams !== null) {
            if (!$request->hasHeader('Content-Type')) {
                $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
            }
            $request->setBody(http_build_query($context->options->formParams));
        } elseif ($context->options->body !== null) {
            $request->setBody($context->options->body);
        }

        $timeoutCancellation = new TimeoutCancellation($context->options->timeout);
        $cancellation = $context->options->cancellation instanceof \Amp\Cancellation
            ? new CompositeCancellation($timeoutCancellation, $context->options->cancellation)
            : $timeoutCancellation;
        $client = $context->options->followRedirects ? $this->client : $this->noRedirectClient;

        try {
            $response = $client->request($request, $cancellation);
            $body = $this->consumeBody($response->getBody(), $context->options->sink, $cancellation);
            $durationMs = (hrtime(true) - $startedAt) / 1000000;

            $httpResponse = new HttpResponse(
                $response->getStatus(),
                $response->getHeaders(),
                $body,
                $durationMs,
                $context->requestId,
            );

            $this->notifyAfterResponse($context, $httpResponse);

            return $httpResponse;
        } catch (\Throwable $exception) {
            $this->notifyAfterFailure($context, $exception);
            throw $exception;
        }
    }

    /**
     * 处理sendConcurrent
     * @param array $requests
     * @param int $concurrency
     * @return array
     */
    public function sendConcurrent(array $requests, int $concurrency = 5): array
    {
        if ($concurrency < 1) {
            $concurrency = 1;
        }

        $chunks = array_chunk($requests, $concurrency, true);
        $results = [];

        foreach ($chunks as $chunk) {
            $futures = [];
            foreach ($chunk as $key => $request) {
                $futures[$key] = async(function () use ($request) {
                    return $this->send(
                        $request['method'] ?? 'GET',
                        $request['url'],
                        $request['options'] ?? new RequestOptions(),
                    );
                });
            }

            $results += Future\await($futures);
        }

        return $results;
    }

    /**
     * 处理appendQuery
     * @param string $url
     * @param array $query
     * @return string
     */
    private function appendQuery(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query($query);
    }

    /**
     * 处理consumeBody
     * @param Payload $payload
     * @param string $sink
     * @param \Amp\Cancellation $cancellation
     * @return string
     */
    protected function consumeBody(Payload $payload, ?string $sink, ?\Amp\Cancellation $cancellation): string
    {
        if ($sink === null) {
            return $payload->buffer($cancellation);
        }

        $handle = fopen($sink, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open sink file: {$sink}");
        }

        try {
            while (($chunk = $payload->read($cancellation)) !== null) {
                if (fwrite($handle, $chunk) === false) {
                    throw new \RuntimeException("Unable to write sink file: {$sink}");
                }
            }
        } finally {
            fclose($handle);
        }

        return '';
    }

    /**
     * 应用BeforeSendInterceptors
     * @param HttpRequestContext $context
     * @return HttpRequestContext
     */
    protected function applyBeforeSendInterceptors(HttpRequestContext $context): HttpRequestContext
    {
        $interceptors = $this->contextInterceptors($context);

        foreach ($interceptors as $interceptor) {
            $context = $interceptor->beforeSend($context);
            $context->interceptors = $interceptors;
        }

        return $context;
    }

    /**
     * 处理通知After响应
     * @param HttpRequestContext $context
     * @param HttpResponse $response
     * @return void
     */
    protected function notifyAfterResponse(HttpRequestContext $context, HttpResponse $response): void
    {
        foreach ($this->contextInterceptors($context) as $interceptor) {
            $interceptor->afterResponse($context, $response);
        }
    }

    /**
     * 处理通知After失败
     * @param HttpRequestContext $context
     * @param \Throwable $exception
     * @return void
     */
    protected function notifyAfterFailure(HttpRequestContext $context, \Throwable $exception): void
    {
        foreach ($this->contextInterceptors($context) as $interceptor) {
            $interceptor->afterFailure($context, $exception);
        }
    }

    /**
     * @return HttpClientInterceptor[]
     */
    protected function resolveInterceptors(HttpRequestContext $context): array
    {
        return $this->interceptorRegistry()->resolve($context);
    }

    /**
     * 处理interceptorRegistry
     * @return HttpClientInterceptorRegistry
     */
    protected function interceptorRegistry(): HttpClientInterceptorRegistry
    {
        return $this->interceptorRegistry;
    }

    /**
     * 处理应用上下文
     * @return AppContext
     */
    protected function appContext(): AppContext
    {
        return $this->context;
    }

    /**
     * @return HttpClientInterceptor[]
     */
    protected function contextInterceptors(HttpRequestContext $context): array
    {
        if ($context->interceptors === null) {
            $context->interceptors = $this->resolveInterceptors($context);
        }

        return $context->interceptors;
    }
}
