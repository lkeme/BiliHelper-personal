<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Util\Exceptions\NoLoginException;

final class AuthFailureClassifier
{
    public function isAuthFailure(array $response): bool
    {
        return $this->reason($response) !== null;
    }

    public function assertNotAuthFailure(array $response, string $fallbackMessage = '账号未登录'): void
    {
        $reason = $this->reason($response);
        if ($reason === null) {
            return;
        }

        throw new NoLoginException($reason !== '' ? $reason : $fallbackMessage);
    }

    public function reason(array $response): ?string
    {
        $code = (int)($response['code'] ?? 0);
        $message = trim((string)($response['message'] ?? $response['msg'] ?? ''));

        if ($code === -101) {
            return $message !== '' ? $message : '账号未登录';
        }

        if ($code === -111) {
            return $message !== '' ? $message : 'csrf 校验失败';
        }

        $normalized = strtolower($message);
        foreach ([
            '账号未登录',
            '未登录',
            'user not login',
            'csrf 校验失败',
            'csrf request error',
            '请先登录',
        ] as $needle) {
            if ($needle !== '' && str_contains($normalized, strtolower($needle))) {
                return $message !== '' ? $message : $needle;
            }
        }

        return null;
    }
}
