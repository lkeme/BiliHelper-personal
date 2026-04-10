<?php declare(strict_types=1);

namespace Bhp\Api\Support;

use Bhp\Request\Request;

final class ApiTransport
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function getText(
        Request $request,
        string $os,
        string $url,
        array $payload,
        array $headers,
        float $timeout = 30.0,
    ): string
    {
        return $request->getText($os, $url, $payload, $headers, $timeout);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function postText(
        Request $request,
        string $os,
        string $url,
        array $payload,
        array $headers,
        float $timeout = 30.0,
    ): string
    {
        return $request->postText($os, $url, $payload, $headers, $timeout);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function postJsonBodyText(
        Request $request,
        string $os,
        string $url,
        array $payload,
        array $headers,
        float $timeout = 30.0,
    ): string
    {
        return $request->postJsonBodyText($os, $url, $payload, $headers, $timeout);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    public function postTextWithQuery(
        Request $request,
        string $os,
        string $url,
        array $payload,
        array $query,
        array $headers,
        float $timeout = 30.0,
    ): string {
        return $request->postTextWithQuery($os, $url, $payload, $query, $headers, $timeout);
    }
}
