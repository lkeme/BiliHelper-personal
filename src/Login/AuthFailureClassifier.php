<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Util\Exceptions\NoLoginException;

final class AuthFailureClassifier
{
    /**
     * 判断认证失败是否满足条件
     * @param array $response
     * @return bool
     */
    public function isAuthFailure(array $response): bool
    {
        return $this->reason($response) !== null;
    }

    /**
     * 断言Not认证失败
     * @param array $response
     * @param string $fallbackMessage
     * @return void
     */
    public function assertNotAuthFailure(array $response, string $fallbackMessage = '账号未登录'): void
    {
        $reason = $this->reason($response);
        if ($reason === null) {
            return;
        }

        throw new NoLoginException($reason !== '' ? $reason : $fallbackMessage);
    }

    /**
     * 处理reason
     * @param array $response
     * @return ?string
     */
    public function reason(array $response): ?string
    {
        $code = $this->resolveCode($response);
        $message = $this->resolveMessage($response);
        $stringCode = $this->resolveStringCode($response);

        if ($code === -101) {
            return $message !== '' ? $message : '账号未登录';
        }

        if ($code === -111) {
            return $message !== '' ? $message : 'csrf 校验失败';
        }

        if ($stringCode === 'unauthenticated') {
            return $message !== '' ? $message : '账号未登录';
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

    /**
     * 解析状态码
     * @param array $response
     * @return int
     */
    private function resolveCode(array $response): int
    {
        foreach (['code', 'errno', 'errcode'] as $key) {
            if (isset($response[$key]) && is_numeric($response[$key])) {
                return (int)$response[$key];
            }
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        if (($data['isLogin'] ?? true) === false) {
            return -101;
        }

        return 0;
    }

    /**
     * 解析String状态码
     * @param array $response
     * @return string
     */
    private function resolveStringCode(array $response): string
    {
        foreach (['code', 'errno', 'errcode'] as $key) {
            $value = trim((string)($response[$key] ?? ''));
            if ($value !== '' && !is_numeric($value)) {
                return strtolower($value);
            }
        }

        return '';
    }

    /**
     * 解析消息
     * @param array $response
     * @return string
     */
    private function resolveMessage(array $response): string
    {
        foreach (['message', 'msg', 'errmsg'] as $key) {
            $value = trim((string)($response[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        if (($data['isLogin'] ?? true) === false) {
            return '账号未登录';
        }

        return '';
    }
}
