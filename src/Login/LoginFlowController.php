<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Util\Exceptions\LoginException;

final class LoginFlowController
{
    /**
     * @param callable():void $accountLogin
     * @param callable():void $smsLogin
     */
    public function runMode(int $modeId, callable $accountLogin, callable $smsLogin): void
    {
        switch ($modeId) {
            case 1:
                $accountLogin();
                return;
            case 2:
                $smsLogin();
                return;
            case 3:
                throw new LoginException('已不支持扫码登录模式，当前推荐短信登录模式', 6 * 3600);
            default:
                throw new LoginException('登录模式配置错误', 6 * 3600);
        }
    }

    /**
     * @param array<string, mixed> $flow
     * @param callable(string):(?array<string, string>) $fetchCaptchaResult
     * @param callable(float):void $scheduleAfter
     * @param callable():void $clearPendingFlow
     * @param callable(array<string, mixed>):void $finalizePendingFlow
     * @param callable(string, string, string):void $accountLogin
     * @param callable(string, string, string, string, string):(?array<string, mixed>) $sendSms
     * @param callable(string):string $promptCode
     * @param callable(array<string, mixed>, string):void $completeSmsLogin
     * @param callable(string):bool $validateQrAuthCode
     */
    public function resume(
        array $flow,
        callable $fetchCaptchaResult,
        callable $scheduleAfter,
        callable $clearPendingFlow,
        callable $finalizePendingFlow,
        callable $accountLogin,
        callable $sendSms,
        callable $promptCode,
        callable $completeSmsLogin,
        callable $validateQrAuthCode,
    ): void {
        switch ($flow['type'] ?? null) {
            case 'account_captcha':
                $captcha = $fetchCaptchaResult((string) $flow['challenge']);
                if ($captcha === null) {
                    $scheduleAfter(2.0);
                    return;
                }

                $accountLogin($captcha['validate'], $captcha['challenge'], '账密模式(行为验证码)');
                $finalizePendingFlow($flow);
                return;
            case 'sms_captcha':
                $captcha = $fetchCaptchaResult((string) $flow['challenge']);
                if ($captcha === null) {
                    $scheduleAfter(2.0);
                    return;
                }

                $payload = $sendSms(
                    (string) $flow['phone'],
                    (string) $flow['cid'],
                    $captcha['validate'],
                    $captcha['challenge'],
                    (string) $flow['recaptcha_token'],
                );
                if ($payload === null) {
                    $finalizePendingFlow($flow);
                    return;
                }

                $code = $promptCode('请输入收到的短信验证码: ');
                if ($code === '') {
                    throw new LoginException('短信验证码不能为空', 3600);
                }

                $completeSmsLogin($payload, $code);
                $finalizePendingFlow($flow);
                return;
            case 'qrcode_poll':
                if ((int) ($flow['expires_at'] ?? 0) < time()) {
                    $clearPendingFlow();
                    throw new LoginException('扫码失败 二维码已失效', 300);
                }

                if ($validateQrAuthCode((string) $flow['auth_code'])) {
                    $clearPendingFlow();
                    return;
                }

                $scheduleAfter(3.0);
                return;
            default:
                $clearPendingFlow();
        }
    }
}
