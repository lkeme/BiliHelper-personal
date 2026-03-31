<?php declare(strict_types=1);

namespace Bhp\Login;

final class LoginSessionCoordinator
{
    public function __construct(
        private readonly LoginFlowController $flowController,
    ) {
    }

    /**
     * @param callable():void $login
     * @param callable():void $keepLogin
     */
    public function initialize(string $token, string $refreshToken, callable $login, callable $keepLogin): void
    {
        if ($token === '' || $refreshToken === '') {
            $login();
        }

        $keepLogin();
    }

    /**
     * @param callable(int):void $prepareCredentials
     * @param callable():void $accountLogin
     * @param callable():void $smsLogin
     */
    public function dispatchMode(
        int $modeId,
        callable $prepareCredentials,
        callable $accountLogin,
        callable $smsLogin,
    ): void {
        $prepareCredentials($modeId);
        $this->flowController->runMode($modeId, $accountLogin, $smsLogin);
    }

    /**
     * @param callable():void $loginFallback
     * @param callable():void $patchCookie
     */
    public function maintainSession(
        string $token,
        string $refreshToken,
        LoginTokenLifecycleService $tokenLifecycleService,
        callable $loginFallback,
        callable $patchCookie,
    ): void {
        $tokenLifecycleService->ensureSessionAlive($token, $refreshToken, $loginFallback, $patchCookie);
    }
}
