<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Login\LoginRuntimeState;

final class LoginPendingFlowResumeService
{
    /**
     * 初始化 LoginPendingFlowResumeService
     * @param LoginFlowController $flowController
     * @param LoginPendingFlowLifecycleService $pendingFlowLifecycleService
     */
    public function __construct(
        private readonly LoginFlowController $flowController,
        private readonly LoginPendingFlowLifecycleService $pendingFlowLifecycleService,
    ) {
    }

    /**
     * @param array<string, mixed> $flow
     * @param callable(float):void $scheduleAfter
     * @param callable():void $clearPendingFlow
     * @param callable(array<string, mixed>):void $finalizePendingFlow
     * @param callable(string, string, string):void $resumeAccountLogin
     * @param callable(string, string, string, string, string):(?array<string, mixed>) $sendSms
     * @param callable(string):string $promptCode
     * @param callable(array<string, mixed>, string):void $completeSmsLogin
     */
    public function resume(
        array $flow,
        LoginRuntimeState $state,
        callable $scheduleAfter,
        callable $clearPendingFlow,
        callable $finalizePendingFlow,
        callable $resumeAccountLogin,
        callable $sendSms,
        callable $promptCode,
        callable $completeSmsLogin,
    ): void {
        $this->flowController->resume(
            $flow,
            fn (string $challenge): ?array => $this->pendingFlowLifecycleService->fetchCaptchaResult($state, $challenge),
            $scheduleAfter,
            $clearPendingFlow,
            $finalizePendingFlow,
            $resumeAccountLogin,
            $sendSms,
            $promptCode,
            $completeSmsLogin,
        );
    }
}
