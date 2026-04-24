<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Passport\ApiCaptcha;
use Bhp\Util\Exceptions\LoginException;

final class LoginManualAssistService
{
    private const TYPE_GEETEST = 'geetest';
    private const TYPE_SMS_CODE = 'sms_code';
    private const TYPE_RISK_SMS_CODE = 'risk_sms_code';

    public function __construct(
        private readonly LoginCaptchaService $captchaService,
        private readonly LoginPendingFlowStore $pendingFlowStore,
        private readonly ApiCaptcha $apiCaptcha,
    ) {
    }

    public function enabled(): bool
    {
        try {
            $this->captchaService->assertCaptchaServiceReady();
            return true;
        } catch (LoginException) {
            return false;
        }
    }

    /**
     * @param array{gt:string,challenge:string} $captchaInfo
     * @return array{id:string,type:string,url:string,expires_at:int}
     */
    public function openGeetestFlow(array $captchaInfo, string $title, string $message, int $ttlSeconds = 120): array
    {
        return $this->openFlow(self::TYPE_GEETEST, [
            'gt' => $captchaInfo['gt'],
            'challenge' => $captchaInfo['challenge'],
            'title' => $title,
            'message' => $message,
        ], $ttlSeconds);
    }

    /**
     * @return array{id:string,type:string,url:string,expires_at:int}
     */
    public function openSmsCodeFlow(string $title, string $message, string $maskedPhone = '', int $ttlSeconds = 300): array
    {
        return $this->openFlow(self::TYPE_SMS_CODE, [
            'title' => $title,
            'message' => $message,
            'masked_phone' => $maskedPhone,
        ], $ttlSeconds);
    }

    /**
     * @return array{id:string,type:string,url:string,expires_at:int}
     */
    public function openRiskSmsCodeFlow(string $title, string $message, string $maskedPhone = '', int $ttlSeconds = 300): array
    {
        return $this->openFlow(self::TYPE_RISK_SMS_CODE, [
            'title' => $title,
            'message' => $message,
            'masked_phone' => $maskedPhone,
        ], $ttlSeconds);
    }

    /**
     * @param array{id:string,type:string,url:string,expires_at:int} $flow
     * @param callable(string):void $progressLogger
     * @return array{validate:string,challenge:string,seccode:string}
     */
    public function waitForGeetestResult(array $flow, callable $progressLogger): array
    {
        try {
            $result = $this->waitForFlowState($flow, ['resolved'], $progressLogger, '行为验证码等待超时，本次运行结束，请重新开始登录流程');
        } catch (LoginException $exception) {
            $this->clearConsumedFlow($flow['id']);
            throw $exception;
        }

        $payload = is_array($result['result'] ?? null) ? $result['result'] : [];
        $validate = trim((string)($payload['validate'] ?? ''));
        $challenge = trim((string)($payload['challenge'] ?? ''));
        $seccode = trim((string)($payload['seccode'] ?? ''));
        if ($validate === '' || $challenge === '' || $seccode === '') {
            $this->clearConsumedFlow($flow['id']);
            throw new LoginException('登录助手返回的行为验证码结果无效', 300);
        }

        $this->clearConsumedFlow($flow['id']);

        return [
            'validate' => $validate,
            'challenge' => $challenge,
            'seccode' => $seccode,
        ];
    }

    /**
     * @param array{id:string,type:string,url:string,expires_at:int} $flow
     * @param callable(string):void $progressLogger
     */
    public function waitForCodeResult(array $flow, callable $progressLogger): string
    {
        try {
            $result = $this->waitForFlowState($flow, ['submitted', 'resolved'], $progressLogger, '短信验证码等待超时，本次运行结束，请重新开始登录流程');
        } catch (LoginException $exception) {
            $this->clearConsumedFlow($flow['id']);
            throw $exception;
        }

        $code = trim((string)($result['submitted_code'] ?? ''));
        if ($code === '') {
            $this->clearConsumedFlow($flow['id']);
            throw new LoginException('登录助手返回的短信验证码为空', 300);
        }

        $this->clearConsumedFlow($flow['id']);
        return $code;
    }

    /**
     * @param array{id:string,type:string,url:string,expires_at:int} $flow
     * @param list<string> $acceptedStates
     * @return array<string, mixed>
     */
    private function waitForFlowState(
        array $flow,
        array $acceptedStates,
        callable $progressLogger,
        string $timeoutMessage,
    ): array {
        $nextProgressLogAt = time() + 10;
        while (true) {
            if ((int) $flow['expires_at'] < time()) {
                throw new LoginException($timeoutMessage, 300);
            }

            $current = $this->fetchFlow($flow['id']);
            $state = trim((string)($current['status'] ?? 'pending'));
            if (\in_array($state, $acceptedStates, true)) {
                return $current;
            }

            if ($state === 'failed') {
                $message = trim((string)($current['message'] ?? '登录助手处理失败'));
                throw new LoginException($message !== '' ? $message : '登录助手处理失败', 300);
            }

            if ($state === 'expired' || $state === 'missing') {
                throw new LoginException($timeoutMessage, 300);
            }

            $now = time();
            if ($now >= $nextProgressLogAt) {
                $remaining = max(0, (int)$flow['expires_at'] - $now);
                $progressLogger(sprintf('仍在等待登录助手页面提交结果，剩余约 %d 秒', $remaining));
                $nextProgressLogAt = $now + 10;
            }

            usleep(2_000_000);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchFlow(string $flowId): array
    {
        $response = $this->apiCaptcha->fetchManualFlow($this->baseUrl(), $flowId);
        if (($response['code'] ?? null) === 10020 && is_array($response['data'] ?? null)) {
            return $response['data'];
        }

        if (($response['code'] ?? null) === 10021) {
            return ['status' => 'missing'];
        }

        $message = trim((string)($response['message'] ?? '登录助手状态获取失败'));
        throw new LoginException($message !== '' ? $message : '登录助手状态获取失败', 300);
    }

    /**
     * @param array<string, string> $payload
     * @return array{id:string,type:string,url:string,expires_at:int}
     */
    private function openFlow(string $type, array $payload, int $ttlSeconds): array
    {
        $this->captchaService->assertCaptchaServiceReady();

        $flowId = bin2hex(random_bytes(16));
        $expiresAt = time() + max(60, $ttlSeconds);
        $response = $this->apiCaptcha->openManualFlow($this->baseUrl(), array_merge($payload, [
            'id' => $flowId,
            'type' => $type,
            'expires_at' => (string)$expiresAt,
        ]));
        if (($response['code'] ?? null) !== 10010) {
            $message = trim((string)($response['message'] ?? '创建登录助手流程失败'));
            throw new LoginException($message !== '' ? $message : '创建登录助手流程失败', 600);
        }

        $this->pendingFlowStore->save([
            'type' => $type,
            'id' => $flowId,
            'expires_at' => $expiresAt,
        ]);

        return [
            'id' => $flowId,
            'type' => $type,
            'url' => $this->captchaService->buildManualAssistUrl($flowId),
            'expires_at' => $expiresAt,
        ];
    }

    private function clearConsumedFlow(string $flowId): void
    {
        $this->pendingFlowStore->clear();
        $this->apiCaptcha->clearManualFlow($this->baseUrl(), $flowId);
    }

    private function baseUrl(): string
    {
        return (string)$this->captchaService->serviceBaseUrl();
    }
}
