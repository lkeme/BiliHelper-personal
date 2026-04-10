<?php declare(strict_types=1);

namespace Bhp\Api\Support;

use Bhp\Request\Request;
use Throwable;

abstract class AbstractApiClient
{
    public function __construct(
        private readonly Request $request,
        private readonly ?ApiTransport $transport = null,
        private readonly ?ApiResponseDecoder $decoder = null,
    ) {
    }

    protected function request(): Request
    {
        return $this->request;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function decodeGet(
        string $os,
        string $url,
        array $payload,
        array $headers,
        string $label,
        ?ApiResponseNormalizerInterface $normalizer = null,
        float $timeout = 30.0,
    ): array {
        try {
            $raw = $this->transport()->getText($this->request(), $os, $url, $payload, $headers, $timeout);
        } catch (Throwable $throwable) {
            return $this->transportFailure($label, $throwable);
        }

        return $this->decoder()->decodeJson($raw, $label, $normalizer);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function decodePost(
        string $os,
        string $url,
        array $payload,
        array $headers,
        string $label,
        ?ApiResponseNormalizerInterface $normalizer = null,
        float $timeout = 30.0,
    ): array {
        try {
            $raw = $this->transport()->postText($this->request(), $os, $url, $payload, $headers, $timeout);
        } catch (Throwable $throwable) {
            return $this->transportFailure($label, $throwable);
        }

        return $this->decoder()->decodeJson($raw, $label, $normalizer);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function decodePostJson(
        string $os,
        string $url,
        array $payload,
        array $headers,
        string $label,
        ?ApiResponseNormalizerInterface $normalizer = null,
        float $timeout = 30.0,
    ): array {
        try {
            $raw = $this->transport()->postJsonBodyText($this->request(), $os, $url, $payload, $headers, $timeout);
        } catch (Throwable $throwable) {
            return $this->transportFailure($label, $throwable);
        }

        return $this->decoder()->decodeJson($raw, $label, $normalizer);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function decodePostWithQuery(
        string $os,
        string $url,
        array $payload,
        array $query,
        array $headers,
        string $label,
        ?ApiResponseNormalizerInterface $normalizer = null,
        float $timeout = 30.0,
    ): array {
        try {
            $raw = $this->transport()->postTextWithQuery($this->request(), $os, $url, $payload, $query, $headers, $timeout);
        } catch (Throwable $throwable) {
            return $this->transportFailure($label, $throwable);
        }

        return $this->decoder()->decodeJson($raw, $label, $normalizer);
    }

    /**
     * @return array<string, mixed>
     */
    protected function transportFailure(string $label, Throwable $throwable): array
    {
        return [
            'code' => -500,
            'message' => "{$label} 请求失败: {$throwable->getMessage()}",
            'data' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeWith(
        callable $callback,
        string $label,
        ?ApiResponseNormalizerInterface $normalizer = null,
    ): array {
        try {
            $raw = $callback();
        } catch (Throwable $throwable) {
            return $this->transportFailure($label, $throwable);
        }

        if (!is_string($raw)) {
            return [
                'code' => -500,
                'message' => "{$label} 响应格式异常",
                'data' => [],
            ];
        }

        return $this->decoder()->decodeJson($raw, $label, $normalizer);
    }

    protected function transport(): ApiTransport
    {
        return $this->transport ?? new ApiTransport();
    }

    protected function decoder(): ApiResponseDecoder
    {
        return $this->decoder ?? new ApiResponseDecoder();
    }
}
