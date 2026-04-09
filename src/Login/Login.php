<?php declare(strict_types=1);

namespace Bhp\Login;

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

use Bhp\Api\Passport\ApiLogin;
use Bhp\Api\Passport\ApiOauth2;
use Bhp\Api\Response\LoginDecision;
use Bhp\Api\Response\LoginTokenBundle;
use Bhp\Api\WWW\ApiMain;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Util\Common\Common;
use Bhp\Util\Exceptions\LoginException;
use Bhp\Util\Exceptions\NoLoginException;
use Bhp\Util\Exceptions\RequestException;

class Login extends BasePlugin implements PluginTaskInterface
{
    /**
     * 插件信息
     * @var array<string, mixed>|null
     */

    /**
     * @var string|null
     */
    protected ?string $username = '';
    /**
     * @var string|null
     */
    protected ?string $password = '';

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $pendingLoginFlow = null;
    private ?LoginCaptchaService $captchaService = null;
    private ?LoginCookieMaintenanceService $cookieMaintenanceService = null;
    private ?LoginCookiePatchService $cookiePatchService = null;
    private ?LoginCredentialService $credentialService = null;
    private ?LoginFlowController $flowController = null;
    private ?LoginAuthenticationService $authenticationService = null;
    private ?LoginDecisionApplierService $decisionApplierService = null;
    private ?LoginModeExecutor $modeExecutor = null;
    private ?LoginPromptService $promptService = null;
    private ?LoginResponseService $responseService = null;
    private ?LoginSmsService $smsService = null;
    private ?LoginPendingFlowFactory $pendingFlowFactory = null;
    private ?LoginPendingFlowStore $pendingFlowStore = null;
    private ?LoginPendingFlowStateService $pendingFlowStateService = null;
    private ?LoginPendingFlowLifecycleService $pendingFlowLifecycleService = null;
    private ?LoginPendingFlowResumeService $pendingFlowResumeService = null;
    private ?LoginSessionCoordinator $sessionCoordinator = null;
    private ?LoginTokenLifecycleService $tokenLifecycleService = null;
    private ?LoginRuntimeState $runtimeState = null;
    private ?LoginGateStateService $gateStateService = null;
    private ?ApiLogin $apiLogin = null;
    private ?ApiOauth2 $apiOauth2 = null;
    private ?ApiMain $apiMain = null;

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, true);
    }

    /**
     */
    public function runOnce(): \Bhp\Scheduler\TaskResult
    {
        $this->resetTaskResult();
        try {
            if ($this->hasPendingLoginFlow()) {
                $this->resumePendingLoginFlow();
                if ($this->hasPendingLoginFlow()) {
                    return $this->resolveTaskResult(\Bhp\Scheduler\TaskResult::after(2.0));
                }

                $this->keepLogin();
                return $this->resolveTaskResult(\Bhp\Scheduler\TaskResult::after(7200));
            }

            if (!$this->hasLoginTokens()) {
                $this->initLogin();
                if ($this->hasPendingLoginFlow()) {
                    return $this->resolveTaskResult(\Bhp\Scheduler\TaskResult::after(2.0));
                }

                $this->assertLoginReady('No valid login state available');
                return $this->resolveTaskResult(\Bhp\Scheduler\TaskResult::after(7200));
            }

            $this->keepLogin();
            if (!$this->gateStateService()->authReady()) {
                return $this->retryAfter(300, '登录就绪态不完整');
            }
        } catch (RequestException $e) {
            return $this->retryAfterRequestException($e, '登录', 10 * 60);
        } catch (LoginException $e) {
            if ($this->hasPendingLoginFlow()) {
                $this->warning("登录: {$e->getMessage()}");
                return $this->retryAfter($e->getRetryAfterSeconds());
            }

            if (!$this->hasLoginTokens()) {
                throw new NoLoginException($e->getMessage());
            }
            $this->warning("登录: {$e->getMessage()}");
            return $this->retryAfter($e->getRetryAfterSeconds());
        }

        return $this->resolveTaskResult(\Bhp\Scheduler\TaskResult::after(7200));
    }

    protected function hasLoginTokens(): bool
    {
        return $this->auth('access_token') !== '' && $this->auth('refresh_token') !== '';
    }

    protected function hasPendingLoginFlow(): bool
    {
        return $this->currentPendingLoginFlow() !== null;
    }

    /**
     * @param array<string, mixed> $flow
     */
    protected function setPendingLoginFlow(array $flow): void
    {
        $this->pendingFlowStateService()->set($this->state(), $flow);
        $this->syncRuntimeState();
    }

    protected function clearPendingLoginFlow(): void
    {
        $this->pendingFlowStateService()->clear($this->state());
        $this->syncRuntimeState();
    }

    /**
     * 初始化登录
     */
    protected function initLogin(): void
    {
        if (!$this->hasConfiguredLoginFallback()) {
            throw new NoLoginException('No login method is configured');
        }

        $this->info('启动登录程序');
        $this->info('准备载入登录令牌');
        $this->login();
        if ($this->hasPendingLoginFlow()) {
            return;
        }

        if (!$this->hasLoginTokens()) {
            throw new NoLoginException('No valid login state available');
        }

        $this->keepLogin();
    }

    /**
     * 登录控制中心
     */
    protected function login(): void
    {
        $this->sessionCoordinator()->dispatchMode(
            (int)$this->config('login_mode.mode'),
            function (int $modeId): void {
                $this->checkLogin($modeId);
            },
            function (): void {
                $this->accountLogin();
            },
            function (): void {
                $this->smsLogin();
            },
        );
    }

    /**
     * 保持认证
     */
    protected function keepLogin(): void
    {
        $this->sessionCoordinator()->maintainSession(
            $this->auth('access_token'),
            $this->auth('refresh_token'),
            $this->tokenLifecycleService(),
            function (): void {
                if (!$this->hasConfiguredLoginFallback()) {
                    throw new NoLoginException('登录令牌已失效，且未配置可回退登录方式');
                }

                $this->login();
                if ($this->hasPendingLoginFlow()) {
                    throw new LoginException('Login flow is still pending', 2);
                }

                if (!$this->hasLoginTokens()) {
                    throw new NoLoginException('No valid login state available');
                }
            },
            function (): void {
                $this->patchCookie();
            },
        );
    }

    protected function hasConfiguredLoginFallback(): bool
    {
        $modeId = (int)$this->config('login_mode.mode');
        $username = trim((string)$this->config('login_account.username'));
        $password = trim((string)$this->config('login_account.password'));

        return match ($modeId) {
            1 => $username !== '' && $password !== '',
            2 => $username !== '',
            default => false,
        };
    }

    protected function assertLoginReady(string $message): void
    {
        if ($this->hasPendingLoginFlow() || !$this->gateStateService()->authReady()) {
            throw new NoLoginException($message);
        }
    }

    protected function resumePendingLoginFlow(): void
    {
        $flow = $this->currentPendingLoginFlow();
        if ($flow === null) {
            return;
        }

        $this->pendingFlowResumeService()->resume(
            $flow,
            $this->state(),
            function (float $seconds): void {
                $this->scheduleAfter($seconds);
            },
            function (): void {
                $this->clearPendingLoginFlow();
            },
            function (array $flow): void {
                $current = $this->currentPendingLoginFlow();
                if ($current !== null && $current === $flow) {
                    $this->clearPendingLoginFlow();
                }
            },
            function (string $validate, string $challenge, string $mode): void {
                $this->checkLogin(1);
                $this->accountLogin($validate, $challenge, $mode);
            },
            fn (string $phone, string $cid, string $validate, string $challenge, string $recaptchaToken): ?array => $this->authenticationService()->sendSms(
                $phone,
                $cid,
                $validate,
                $challenge,
                $recaptchaToken,
                function (string $phone, string $cid, string $targetUrl): void {
                    $this->warning("此次请求需要行为验证码");
                    $this->beginSmsCaptchaLogin($phone, $cid, $targetUrl);
                },
            ),
            fn (string $message): string => $this->cliInput($message),
            function (array $payload, string $code): void {
                $response = $this->authenticationService()->submitSmsLogin($payload, $code);
                $this->completeLoginResponse('短信模式', $response);
            },
        );
    }

    protected function beginCaptchaLogin(string $targetUrl): void
    {
        $this->invalidateSessionAuth();
        $result = $this->pendingFlowLifecycleService()->beginAccountCaptcha(
            $this->state(),
            $targetUrl,
            (string)$this->config('login_captcha.url'),
            time() + 120,
        );
        $this->syncRuntimeState();
        $this->announcePendingCaptcha((string)$result['display_url'], (float)$result['delay_seconds']);
    }

    protected function beginSmsCaptchaLogin(string $phone, string $cid, string $targetUrl): void
    {
        $this->invalidateSessionAuth();
        $result = $this->pendingFlowLifecycleService()->beginSmsCaptcha(
            $this->state(),
            $phone,
            $cid,
            $targetUrl,
            (string)$this->config('login_captcha.url'),
            time() + 120,
        );
        $this->syncRuntimeState();
        $this->announcePendingCaptcha((string)$result['display_url'], (float)$result['delay_seconds']);
    }

    /**
     * @return array<string, string>|null
     */
    protected function fetchCaptchaResult(string $challenge): ?array
    {
        $response = $this->pendingFlowLifecycleService()->fetchCaptchaResult($this->state(), $challenge);
        if ($response !== null) {
            $this->notice('Captcha recognized successfully');
            return $response;
        }

        return null;
    }

    /**
     * CookiePatch
     */
    public function patchCookie(): void
    {
        $result = $this->cookieMaintenanceService()->patchPcCookie(
            $this->getPcCookieValue(),
            $this->fetchHomeHeaders(),
        );

        match ($result['level']) {
            'notice' => $this->notice($result['message']),
            'warning' => $this->warning($result['message']),
            default => $this->info($result['message']),
        };

        if ($result['patched_cookie'] !== null) {
            $this->updateInfo('pc_cookie', $result['patched_cookie']);
        }
    }

    protected function cookiePatchService(): LoginCookiePatchService
    {
        return $this->cookiePatchService ??= new LoginCookiePatchService();
    }

    protected function cookieMaintenanceService(): LoginCookieMaintenanceService
    {
        return $this->cookieMaintenanceService ??= new LoginCookieMaintenanceService($this->cookiePatchService());
    }

    protected function tokenLifecycleService(): LoginTokenLifecycleService
    {
        return $this->tokenLifecycleService ??= new LoginTokenLifecycleService(
            $this->appContext(),
            $this->cookiePatchService(),
            $this->apiOauth2(),
        );
    }

    protected function captchaService(): LoginCaptchaService
    {
        return $this->captchaService ??= new LoginCaptchaService($this->appContext());
    }

    protected function credentialService(): LoginCredentialService
    {
        return $this->credentialService ??= new LoginCredentialService($this->appContext(), $this->apiOauth2());
    }

    protected function promptService(): LoginPromptService
    {
        return $this->promptService ??= new LoginPromptService();
    }

    protected function responseService(): LoginResponseService
    {
        return $this->responseService ??= new LoginResponseService();
    }

    protected function decisionApplierService(): LoginDecisionApplierService
    {
        return $this->decisionApplierService ??= new LoginDecisionApplierService();
    }

    protected function modeExecutor(): LoginModeExecutor
    {
        return $this->modeExecutor ??= new LoginModeExecutor();
    }

    protected function authenticationService(): LoginAuthenticationService
    {
        return $this->authenticationService ??= new LoginAuthenticationService(
            $this->credentialService(),
            $this->modeExecutor(),
            $this->smsService(),
            $this->responseService(),
            $this->apiLogin(),
        );
    }

    protected function smsService(): LoginSmsService
    {
        return $this->smsService ??= new LoginSmsService($this->appContext(), $this->apiLogin());
    }

    protected function pendingFlowStore(): LoginPendingFlowStore
    {
        return $this->pendingFlowStore ??= new LoginPendingFlowStore($this->cache());
    }

    protected function pendingFlowStateService(): LoginPendingFlowStateService
    {
        return $this->pendingFlowStateService ??= new LoginPendingFlowStateService($this->pendingFlowStore());
    }

    protected function pendingFlowLifecycleService(): LoginPendingFlowLifecycleService
    {
        return $this->pendingFlowLifecycleService ??= new LoginPendingFlowLifecycleService(
            $this->captchaService(),
            $this->pendingFlowFactory(),
            $this->pendingFlowStateService(),
            $this->appContext()->log(),
        );
    }

    protected function pendingFlowResumeService(): LoginPendingFlowResumeService
    {
        return $this->pendingFlowResumeService ??= new LoginPendingFlowResumeService(
            $this->flowController(),
            $this->pendingFlowLifecycleService(),
        );
    }

    protected function sessionCoordinator(): LoginSessionCoordinator
    {
        return $this->sessionCoordinator ??= new LoginSessionCoordinator($this->flowController());
    }

    protected function pendingFlowFactory(): LoginPendingFlowFactory
    {
        return $this->pendingFlowFactory ??= new LoginPendingFlowFactory();
    }

    protected function flowController(): LoginFlowController
    {
        return $this->flowController ??= new LoginFlowController();
    }

    protected function gateStateService(): LoginGateStateService
    {
        return $this->gateStateService ??= new LoginGateStateService($this->appContext(), $this->pendingFlowStore());
    }

    /**
     * 更新登录信息
     * @param array $data
     */
    protected function updateLoginInfo(array $data): void
    {
        $bundle = LoginTokenBundle::fromLoginResponse($data);
        $this->updateInfo('access_token', $bundle->accessToken);
        $this->updateInfo('refresh_token', $bundle->refreshToken);
        $this->updateInfo('cookie', $bundle->cookie);
        $this->updateInfo('pc_cookie', $bundle->cookie);
        $this->updateInfo('uid', $bundle->uid, false);
        $this->updateInfo('csrf', $bundle->csrf, false);
        $this->updateInfo('sid', $bundle->sid, false);
    }

    /**
     * 更新信息
     * @param string $key
     * @param mixed $value
     * @param bool $print
     * @param bool $hide
     */
    protected function updateInfo(string $key, mixed $value, bool $print = true, bool $hide = true): void
    {
        $this->setAuth($key, $value);
        if ($print) {
            $this->info(" > " . $this->displayKeyForLog($key, $hide) . ': ' . $this->formatLogValueForLog($key, $value, $hide));
        }
    }

    protected function displayKeyForLog(string $key, bool $hide): string
    {
        if ($hide && $key === 'pc_cookie') {
            return 'pc_cookie(masked)';
        }

        return $key;
    }

    protected function formatLogValueForLog(string $key, mixed $value, bool $hide): string
    {
        $text = is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$hide) {
            return $text;
        }

        if ($key === 'pc_cookie') {
            return $this->maskCookieValuePairs($text);
        }

        return Common::replaceStar($text, 6, 6);
    }

    protected function maskCookieValuePairs(string $cookie): string
    {
        $parts = preg_split('/;/', $cookie, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $masked = [];
        foreach ($parts as $part) {
            $segment = trim($part);
            if ($segment === '') {
                continue;
            }

            if (!str_contains($segment, '=')) {
                $masked[] = $segment . '=[已隐藏]';
                continue;
            }

            [$name] = explode('=', $segment, 2);
            $masked[] = trim($name) . '=[已隐藏]';
        }

        return $masked === [] ? '[已隐藏]' : implode('; ', $masked);
    }

    /**
     * 账密登录
     * @param string $validate
     * @param string $challenge
     * @param string $mode
     */
    protected function completeLoginResponse(string $mode, array $response): void
    {
        $this->authenticationService()->completeLogin($mode, $response, function (LoginDecision $decision, array $rawResponse): void {
            $this->decisionApplierService()->apply(
                $decision,
                $rawResponse,
                function (array $successResponse, string $message): void {
                    $this->info($message);
                    $this->updateLoginInfo($successResponse);
                    $this->info('生成信息配置完毕');
                },
                function (string $captchaUrl, string $message): void {
                    $this->handleCaptchaChallenge($captchaUrl, $message);
                },
            );
        });
    }

    protected function handleCaptchaChallenge(string $captchaUrl, string $message): void
    {
        $this->warning($message);
        $this->beginCaptchaLogin($captchaUrl);
    }

    protected function announcePendingCaptcha(string $displayUrl, float $delaySeconds): void
    {
        $this->info('请在浏览器中打开以下链接，完成验证码识别');
        $this->info($displayUrl);
        $this->info('Please finish captcha verification within 2 minutes');
        $this->scheduleAfter($delaySeconds);
    }

    protected function accountLogin(string $validate = '', string $challenge = '', string $mode = '账密模式'): void
    {
        $this->info("尝试 $mode 登录");
        $this->authenticationService()->accountLogin(
            $this->state(),
            $mode,
            $validate,
            $challenge,
            function (string $mode, array $response): void {
                $this->completeLoginResponse($mode, $response);
            },
        );
    }

    /**
     * 短信登录
     * @param string $mode
     */
    protected function smsLogin(string $mode = '短信模式'): void
    {
        $this->info("尝试 $mode 登录");
        $this->authenticationService()->smsLogin(
            $this->state(),
            (string) $this->config('login_country.code'),
            (bool) $this->config('login_check.phone'),
            function (string $phone, string $countryCode, string $targetUrl): void {
                $this->beginSmsCaptchaLogin($phone, $countryCode, $targetUrl);
            },
            fn (string $message): string => $this->cliInput($message),
            function (array $payload, string $code) use ($mode): void {
                $response = $this->authenticationService()->submitSmsLogin($payload, $code);
                $this->completeLoginResponse($mode, $response);
            },
        );
    }

    /**
     * 检查登录
     */
    protected function checkLogin(int $mode_id): void
    {
        $this->authenticationService()->prepareCredentials($this->state(), $mode_id);
        $this->syncRuntimeState();
    }

    /**
     * 输入短信验证码
     * @param string $msg
     * @param int $max_char
     * @return string
     */
    protected function cliInput(string $msg, int $max_char = 100): string
    {
        return $this->promptService()->prompt($msg, $max_char);
    }

    protected function getPcCookieValue(): string
    {
        return $this->auth('pc_cookie');
    }

    protected function fetchHomeHeaders(): array
    {
        $response = $this->apiMain()->home();
        return is_array($response) ? $response : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function currentPendingLoginFlow(): ?array
    {
        $flow = $this->pendingFlowStateService()->current($this->state());
        $this->syncRuntimeState();
        return $flow;
    }

    protected function state(): LoginRuntimeState
    {
        return $this->runtimeState ??= new LoginRuntimeState(
            (string)($this->username ?? ''),
            (string)($this->password ?? ''),
            is_array($this->pendingLoginFlow) ? $this->pendingLoginFlow : null,
        );
    }

    protected function invalidateSessionAuth(): void
    {
        foreach (['access_token', 'refresh_token', 'cookie', 'pc_cookie', 'uid', 'csrf', 'sid'] as $key) {
            $this->setAuth($key, '');
        }
    }

    protected function syncRuntimeState(): void
    {
        $this->username = $this->state()->username();
        $this->password = $this->state()->password();
        $this->pendingLoginFlow = $this->state()->pendingFlow();
    }

    private function apiLogin(): ApiLogin
    {
        return $this->apiLogin ??= new ApiLogin($this->appContext()->request());
    }

    private function apiOauth2(): ApiOauth2
    {
        return $this->apiOauth2 ??= new ApiOauth2($this->appContext()->request());
    }

    private function apiMain(): ApiMain
    {
        return $this->apiMain ??= new ApiMain($this->appContext()->request());
    }

}

