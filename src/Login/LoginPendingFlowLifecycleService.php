<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Response\QrAuthCode;
use Bhp\Login\LoginPendingFlowFactory;
use Bhp\Login\LoginRuntimeState;
use Bhp\Log\Log;
use Bhp\Util\Exceptions\LoginException;

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
        $expiresAt = (int)($flow['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time()) {
            $this->pendingFlowStateService->clear($state);
            throw new LoginException('验证码识别超时', 300);
        }

        $now = time();
        $result = $this->captchaService->pollCaptchaResult($challenge, $expiresAt);
        if ($result['state'] === 'resolved') {
            return $result['data'];
        }

        if (!is_array($flow)) {
            return null;
        }

        if ($result['state'] === 'pending') {
            $this->maybeLogCaptchaPendingProgress($state, $flow, $now);
            return null;
        }

        $this->maybeLogCaptchaPendingError($state, $flow, $now, $result['message']);

        return null;
    }

    /**
     * @param array<string, mixed> $flow
     */
    private function maybeLogCaptchaPendingProgress(LoginRuntimeState $state, array $flow, int $now): void
    {
        if (!$this->shouldLogCaptchaPendingProgress($flow, $now)) {
            return;
        }

        Log::info(sprintf(
            '验证码结果轮询中，验证码服务访问正常，已等待 %d 秒，2秒后继续',
            $this->captchaPendingWaitedSeconds($flow, $now),
        ));
        $this->touchCaptchaPendingProgress($state, $flow, $now);
    }

    /**
     * @param array<string, mixed> $flow
     */
    private function maybeLogCaptchaPendingError(LoginRuntimeState $state, array $flow, int $now, string $message): void
    {
        if (!$this->shouldLogCaptchaPendingProgress($flow, $now)) {
            return;
        }

        $suffix = $message !== '' ? ": {$message}" : '';
        Log::warning(sprintf(
            '验证码结果查询异常%s，已等待 %d 秒，2秒后继续',
            $suffix,
            $this->captchaPendingWaitedSeconds($flow, $now),
        ));
        $this->touchCaptchaPendingProgress($state, $flow, $now);
    }

    /**
     * @param array<string, mixed> $flow
     */
    private function shouldLogCaptchaPendingProgress(array $flow, int $now): bool
    {
        $startedAt = (int)($flow['started_at'] ?? $now);
        $lastProgressLogAt = (int)($flow['last_progress_log_at'] ?? 0);
        if ($lastProgressLogAt <= 0) {
            return ($now - $startedAt) >= 10;
        }

        return ($now - $lastProgressLogAt) >= 30;
    }

    /**
     * @param array<string, mixed> $flow
     */
    private function touchCaptchaPendingProgress(LoginRuntimeState $state, array $flow, int $now): void
    {
        $flow['started_at'] = (int)($flow['started_at'] ?? $now);
        $flow['last_progress_log_at'] = $now;
        $this->pendingFlowStateService->set($state, $flow);
    }

    /**
     * @param array<string, mixed> $flow
     */
    private function captchaPendingWaitedSeconds(array $flow, int $now): int
    {
        $startedAt = (int)($flow['started_at'] ?? $now);

        return max(0, $now - $startedAt);
    }
}
