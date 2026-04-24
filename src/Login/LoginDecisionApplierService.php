<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Response\LoginDecision;
use Bhp\Util\Exceptions\LoginException;

final class LoginDecisionApplierService
{
    /**
     * @param array<string, mixed> $data
     * @param callable(array<string, mixed>, string):void $onSuccess
     * @param callable(string, string):void $onCaptchaRequired
     * @param callable(array<string, mixed>, string):void $onRiskVerificationRequired
     */
    public function apply(
        LoginDecision $decision,
        array $data,
        callable $onSuccess,
        callable $onCaptchaRequired,
        callable $onRiskVerificationRequired,
    ): void {
        if ($decision->isSuccess()) {
            $onSuccess($data, $decision->message);
            return;
        }

        if ($decision->requiresCaptcha()) {
            $onCaptchaRequired($decision->captchaUrl, $decision->message);
            return;
        }

        if ($decision->requiresRiskVerification()) {
            $onRiskVerificationRequired($data, $decision->message);
            return;
        }

        throw new LoginException($decision->message, $decision->retryAfterSeconds);
    }
}
