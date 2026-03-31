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
     */
    public function apply(
        LoginDecision $decision,
        array $data,
        callable $onSuccess,
        callable $onCaptchaRequired,
    ): void {
        if ($decision->isSuccess()) {
            $onSuccess($data, $decision->message);
            return;
        }

        if ($decision->requiresCaptcha()) {
            $onCaptchaRequired($decision->captchaUrl, $decision->message);
            return;
        }

        throw new LoginException($decision->message, $decision->retryAfterSeconds);
    }
}
