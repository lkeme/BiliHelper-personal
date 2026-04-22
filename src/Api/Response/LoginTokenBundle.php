<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class LoginTokenBundle
{
    /**
     * 初始化 LoginTokenBundle
     * @param string $accessToken
     * @param string $refreshToken
     * @param string $cookie
     * @param string $uid
     * @param string $csrf
     * @param string $sid
     */
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
            throw new \UnexpectedValueException(self::describeInvalidCredentialResponse($response));
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

    /**
     * @param array<string, mixed> $response
     */
    private static function describeInvalidCredentialResponse(array $response): string
    {
        $code = (int)($response['code'] ?? -1);
        $status = isset($response['data']['status']) ? (int)$response['data']['status'] : null;
        $message = trim((string)($response['data']['message'] ?? $response['message'] ?? ''));
        $url = trim((string)($response['data']['url'] ?? ''));
        $parts = ['登录响应缺少有效凭据'];

        $parts[] = 'code=' . $code;

        if ($status !== null) {
            $parts[] = 'status=' . $status;
        }

        if ($message !== '') {
            $parts[] = 'message=' . $message;
        }

        if ($url !== '') {
            $parts[] = 'url=' . $url;
        }

        return \implode('，', $parts);
    }
}
