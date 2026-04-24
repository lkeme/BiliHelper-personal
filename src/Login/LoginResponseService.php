<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Response\LoginDecision;

final class LoginResponseService
{
    /**
     * @param array<string, mixed> $response
     */
    public function decide(string $mode, array $response): LoginDecision
    {
        $code = (int)($response['code'] ?? -1);
        $message = (string)($response['message'] ?? '');

        return match ($code) {
            0 => $this->decideSuccessStatus($mode, $response),
            -105 => LoginDecision::captchaRequired((string)($response['data']['url'] ?? '')),
            -629 => LoginDecision::failure($message, 24 * 3600),
            -2100 => $this->decideRiskVerification($response, '账号启用了设备锁或异地登录，需先完成手机号验证'),
            default => LoginDecision::failure("{$message} (code={$code})", 600),
        };
    }

    /**
     * @param array<string, mixed> $response
     */
    private function decideSuccessStatus(string $mode, array $response): LoginDecision
    {
        $status = $response['data']['status'] ?? null;
        if ($status === null) {
            if (!$this->hasUsableLoginPayload($response)) {
                return LoginDecision::failure(
                    $this->describeMissingCredentialResponse($response),
                    600,
                );
            }

            return LoginDecision::success("$mode 登录成功");
        }

        return match ((int)$status) {
            0 => LoginDecision::success("$mode 登录成功"),
            2 => $this->decideRiskVerification($response, $this->describeRiskVerification($response)),
            3 => $this->decideRiskVerification($response, $this->describePhoneVerification($response)),
            default => LoginDecision::failure(
                $this->describeUnexpectedStatusResponse($response),
                24 * 3600,
            ),
        };
    }

    /**
     * @param array<string, mixed> $response
     */
    private function hasUsableLoginPayload(array $response): bool
    {
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $tokenInfo = is_array($data['token_info'] ?? null) ? $data['token_info'] : [];
        $cookieInfo = is_array($data['cookie_info'] ?? null) ? $data['cookie_info'] : [];
        $cookies = $cookieInfo['cookies'] ?? null;

        if (trim((string)($tokenInfo['access_token'] ?? '')) === '') {
            return false;
        }

        if (trim((string)($tokenInfo['refresh_token'] ?? '')) === '') {
            return false;
        }

        return is_array($cookies) && $cookies !== [];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function describeRiskVerification(array $response): string
    {
        $message = trim((string)($response['data']['message'] ?? ''));
        $url = trim((string)($response['data']['url'] ?? ''));
        $summary = '账号触发安全风控，请先完成手机号验证后再重试';

        if ($message !== '' && !\str_contains($message, '高危异常行为')) {
            $summary .= '，原因: ' . $message;
        }

        return $url === ''
            ? $summary
            : $summary . '。验证页: ' . $url;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function describePhoneVerification(array $response): string
    {
        $url = trim((string)($response['data']['url'] ?? ''));
        $summary = '需要先完成手机号验证';

        return $url === ''
            ? $summary
            : $summary . '。验证页: ' . $url;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function describeMissingCredentialResponse(array $response): string
    {
        $code = (int)($response['code'] ?? -1);
        $message = trim((string)($response['message'] ?? ''));
        $parts = ['登录响应缺少有效凭据'];

        if ($message !== '') {
            $parts[] = 'message=' . $message;
        }

        $parts[] = 'code=' . $code;

        return \implode('，', $parts);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function describeUnexpectedStatusResponse(array $response): string
    {
        $status = (int)($response['data']['status'] ?? -1);
        $message = trim((string)($response['data']['message'] ?? $response['message'] ?? '未知错误'));
        $url = trim((string)($response['data']['url'] ?? ''));
        $parts = ['未知登录状态 status=' . $status];

        if ($message !== '') {
            $parts[] = 'message=' . $message;
        }

        if ($url !== '') {
            $parts[] = 'url=' . $url;
        }

        return \implode('，', $parts);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function decideRiskVerification(array $response, string $message): LoginDecision
    {
        $url = trim((string)($response['data']['url'] ?? ''));
        if ($url === '') {
            return LoginDecision::failure($message, 24 * 3600);
        }

        return LoginDecision::riskVerificationRequired($message);
    }
}
