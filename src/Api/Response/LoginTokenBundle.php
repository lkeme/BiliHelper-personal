<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class LoginTokenBundle
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly string $cookie,
        public readonly string $uid,
        public readonly string $csrf,
        public readonly string $sid,
    ) {
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function fromLoginResponse(array $response): self
    {
        $tokenInfo = $response['data']['token_info'] ?? [];
        $cookies = $response['data']['cookie_info']['cookies'] ?? [];

        $cookieString = '';
        $uid = '';
        $csrf = '';
        $sid = '';

        foreach (is_array($cookies) ? $cookies : [] as $cookie) {
            if (!is_array($cookie)) {
                continue;
            }

            $name = (string)($cookie['name'] ?? '');
            $value = (string)($cookie['value'] ?? '');
            if ($name === '') {
                continue;
            }

            $cookieString .= $name . '=' . $value . ';';
            if ($name === 'DedeUserID') {
                $uid = $value;
            }

            if ($name === 'bili_jct') {
                $csrf = $value;
            }

            if ($name === 'sid') {
                $sid = $value;
            }
        }

        $accessToken = (string)($tokenInfo['access_token'] ?? '');
        $refreshToken = (string)($tokenInfo['refresh_token'] ?? '');
        if ($accessToken === '' || $refreshToken === '' || $cookieString === '') {
            throw new \UnexpectedValueException('登录响应缺少有效凭据');
        }

        return new self(
            $accessToken,
            $refreshToken,
            $cookieString,
            $uid,
            $csrf,
            $sid,
        );
    }
}
