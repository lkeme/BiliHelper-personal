<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Passport\ApiLogin;
use Bhp\Api\Response\LoginDecision;
use Bhp\Login\LoginRuntimeState;
use Bhp\Util\Common\Common;

final class LoginAuthenticationService
{
    public function __construct(
        private readonly LoginCredentialService $credentialService,
        private readonly LoginModeExecutor $modeExecutor,
        private readonly LoginSmsService $smsService,
        private readonly LoginResponseService $responseService,
    ) {
    }

    public function prepareCredentials(LoginRuntimeState $state, int $modeId): void
    {
        $credentials = $this->credentialService->resolveCredentials($modeId);
        $state->setCredentials($credentials['username'], $credentials['password']);
    }

    /**
     * @param callable(string, array<string, mixed>):void $completeLogin
     */
    public function accountLogin(
        LoginRuntimeState $state,
        string $mode,
        string $validate,
        string $challenge,
        callable $completeLogin,
    ): void {
        $this->modeExecutor->executeAccount(
            $state->username(),
            $state->password(),
            $validate,
            $challenge,
            $mode,
            static fn (string $username, string $password, string $validate, string $challenge): array => ApiLogin::passwordLogin($username, $password, $validate, $challenge),
            $completeLogin,
        );
    }

    /**
     * @param callable(string, string, string):void $beginSmsCaptchaLogin
     * @param callable(string):string $promptCode
     * @param callable(array<string, mixed>, string):void $completeSmsLogin
     */
    public function smsLogin(
        LoginRuntimeState $state,
        string $countryCode,
        bool $validatePhone,
        callable $beginSmsCaptchaLogin,
        callable $promptCode,
        callable $completeSmsLogin,
    ): void {
        $this->modeExecutor->executeSms(
            $state->username(),
            $countryCode,
            $validatePhone,
            static fn (string $phone): bool => Common::checkPhone($phone),
            function (string $phone, string $cid) use ($beginSmsCaptchaLogin): ?array {
                return $this->sendSms($phone, $cid, '', '', '', $beginSmsCaptchaLogin);
            },
            $promptCode,
            $completeSmsLogin,
        );
    }

    /**
     * @param callable(string, string, string):void $beginSmsCaptchaLogin
     * @return array<string, mixed>|null
     */
    public function sendSms(
        string $phone,
        string $cid,
        string $validate,
        string $challenge,
        string $recaptchaToken,
        callable $beginSmsCaptchaLogin,
    ): ?array {
        $result = $this->smsService->sendSms($phone, $cid, $validate, $challenge, $recaptchaToken);
        if ($result->requiresCaptcha()) {
            $beginSmsCaptchaLogin($phone, $cid, $result->recaptchaUrl);
            return null;
        }

        return $result->payload;
    }

    /**
     * @param array<string, mixed> $data
     * @param callable(LoginDecision, array<string, mixed>):void $applyDecision
     */
    public function completeLogin(string $mode, array $data, callable $applyDecision): void
    {
        $applyDecision($this->responseService->decide($mode, $data), $data);
    }
}
