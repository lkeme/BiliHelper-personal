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
        return match ((int)($response['code'] ?? -1)) {
            0 => $this->decideSuccessStatus($mode, $response),
            -105 => LoginDecision::captchaRequired((string)($response['data']['url'] ?? '')),
            -629 => LoginDecision::failure("$mode 登录失败: " . (string)($response['message'] ?? ''), 24 * 3600),
            -2100 => LoginDecision::failure("$mode 登录失败: 账号启用了设备锁或异地登录需验证手机号", 24 * 3600),
            default => LoginDecision::failure("$mode 登录失败: 未知错误: " . (string)($response['message'] ?? ''), 24 * 3600),
        };
    }

    /**
     * @param array<string, mixed> $response
     */
    private function decideSuccessStatus(string $mode, array $response): LoginDecision
    {
        $status = $response['data']['status'] ?? null;
        if ($status === null) {
            return LoginDecision::success("$mode 登录成功");
        }

        return match ((int)$status) {
            0 => LoginDecision::success("$mode 登录成功"),
            2 => LoginDecision::failure(
                "$mode 登录失败: " . (string)($response['data']['message'] ?? '您的账号存在高危异常行为'),
                24 * 3600,
            ),
            3 => LoginDecision::failure(
                "$mode 登录失败: 需要验证手机号: " . (string)($response['data']['url'] ?? ''),
                24 * 3600,
            ),
            default => LoginDecision::failure(
                "$mode 登录失败: 未知错误: " . json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                24 * 3600,
            ),
        };
    }
}
