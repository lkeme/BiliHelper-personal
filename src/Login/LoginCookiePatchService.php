<?php declare(strict_types=1);

namespace Bhp\Login;

final class LoginCookiePatchService
{
    /**
     * @param array<string, mixed> $response
     * @return string[]
     */
    public function extractSetCookieHeaders(array $response): array
    {
        foreach ($response as $name => $value) {
            if (strcasecmp($name, 'Set-Cookie') !== 0) {
                continue;
            }

            if (is_array($value)) {
                return array_values(array_filter($value, 'is_string'));
            }

            if (is_string($value) && $value !== '') {
                return [$value];
            }
        }

        return [];
    }

    /**
     * @param string[] $headers
     */
    public function buildCookieStringFromHeaders(array $headers): string
    {
        $cookies = [];
        foreach ($headers as $header) {
            $cookie = trim(explode(';', $header, 2)[0]);
            if ($cookie === '') {
                continue;
            }

            $cookies[] = rtrim($cookie, ';') . ';';
        }

        return implode('', array_reverse($cookies));
    }

    /**
     * @param array<string, mixed> $response
     */
    public function buildPatchedCookie(string $existingCookie, array $response): ?string
    {
        $headers = $this->extractSetCookieHeaders($response);
        if ($headers === []) {
            return null;
        }

        $cookiePatch = $this->buildCookieStringFromHeaders($headers);
        if ($cookiePatch === '' || !str_contains($cookiePatch, 'buvid3')) {
            return null;
        }

        return $existingCookie . $cookiePatch;
    }
}
