<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Response\QrAuthCode;
use Bhp\Login\LoginPendingFlowFactory;
use Bhp\Login\LoginRuntimeState;

final class LoginPendingFlowLifecycleService
{
    public function __construct(
        private readonly LoginCaptchaService $captchaService,
        private readonly LoginPendingFlowFactory $pendingFlowFactory,
        private readonly LoginPendingFlowStateService $pendingFlowStateService,
    ) {
    }

    /**
     * @return array{display_url: string, delay_seconds: int}
     */
    public function beginAccountCaptcha(
        LoginRuntimeState $state,
        string $targetUrl,
        string $captchaBaseUrl,
        int $expiresAt,
    ): array {
        $captchaInfo = $this->captchaService->matchCaptcha($targetUrl);
        $this->captchaService->assertCaptchaServiceReady();
        $this->pendingFlowStateService->set(
            $state,
            $this->pendingFlowFactory->accountCaptcha($captchaInfo, $expiresAt),
        );

        return [
            'display_url' => $captchaBaseUrl . '/geetest?gt=' . $captchaInfo['gt'] . '&challenge=' . $captchaInfo['challenge'],
            'delay_seconds' => 2,
        ];
    }

    /**
     * @return array{display_url: string, delay_seconds: int}
     */
    public function beginSmsCaptcha(
        LoginRuntimeState $state,
        string $phone,
        string $cid,
        string $targetUrl,
        string $captchaBaseUrl,
        int $expiresAt,
    ): array {
        $captchaInfo = $this->captchaService->matchCaptcha($targetUrl);
        $this->captchaService->assertCaptchaServiceReady();
        $this->pendingFlowStateService->set(
            $state,
            $this->pendingFlowFactory->smsCaptcha($phone, $cid, $captchaInfo, $targetUrl, $expiresAt),
        );

        return [
            'display_url' => $captchaBaseUrl . '/geetest?gt=' . $captchaInfo['gt'] . '&challenge=' . $captchaInfo['challenge'],
            'delay_seconds' => 2,
        ];
    }

    /**
     * @return array{qr_url: string, delay_seconds: int}
     */
    public function beginQrcodePolling(LoginRuntimeState $state, QrAuthCode $qrData, int $expiresAt): array
    {
        $this->pendingFlowStateService->set(
            $state,
            $this->pendingFlowFactory->qrcodePoll($qrData, $expiresAt),
        );

        return [
            'qr_url' => $qrData->url,
            'delay_seconds' => 3,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    public function fetchCaptchaResult(LoginRuntimeState $state, string $challenge): ?array
    {
        $flow = $this->pendingFlowStateService->current($state);

        return $this->captchaService->fetchCaptchaResult($challenge, (int)($flow['expires_at'] ?? 0));
    }
}
