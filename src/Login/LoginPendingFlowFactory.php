<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Response\QrAuthCode;

final class LoginPendingFlowFactory
{
    /**
     * @param array{challenge: string} $captchaInfo
     * @return array{type: string, challenge: string, expires_at: int, started_at: int, last_progress_log_at: int}
     */
    public function accountCaptcha(array $captchaInfo, int $expiresAt): array
    {
        $startedAt = time();

        return [
            'type' => 'account_captcha',
            'challenge' => $captchaInfo['challenge'],
            'expires_at' => $expiresAt,
            'started_at' => $startedAt,
            'last_progress_log_at' => 0,
        ];
    }

    /**
     * @param array{challenge: string} $captchaInfo
     * @return array{type: string, phone: string, cid: string, challenge: string, recaptcha_token: string, expires_at: int, started_at: int, last_progress_log_at: int}
     */
    public function smsCaptcha(string $phone, string $cid, array $captchaInfo, string $targetUrl, int $expiresAt): array
    {
        $startedAt = time();

        return [
            'type' => 'sms_captcha',
            'phone' => $phone,
            'cid' => $cid,
            'challenge' => $captchaInfo['challenge'],
            'recaptcha_token' => $this->extractRecaptchaToken($targetUrl),
            'expires_at' => $expiresAt,
            'started_at' => $startedAt,
            'last_progress_log_at' => 0,
        ];
    }

    /**
     * @return array{type: string, auth_code: string, expires_at: int}
     */
    public function qrcodePoll(QrAuthCode $qrData, int $expiresAt): array
    {
        return [
            'type' => 'qrcode_poll',
            'auth_code' => $qrData->authCode,
            'expires_at' => $expiresAt,
        ];
    }

    private function extractRecaptchaToken(string $targetUrl): string
    {
        preg_match('/recaptcha_token=([a-zA-Z0-9_-]+)/', $targetUrl, $matches);

        return $matches[1] ?? '';
    }
}
