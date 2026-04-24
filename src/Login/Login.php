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
use Bhp\Api\Passport\ApiCaptcha;
use Bhp\Api\Passport\ApiOauth2;
use Bhp\Api\Passport\ApiRiskVerification;
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
    private ?LoginRiskVerificationService $riskVerificationService = null;
    private ?LoginSmsService $smsService = null;
    private ?LoginPendingFlowStore $pendingFlowStore = null;
    private ?LoginManualAssistService $manualAssistService = null;
    private ?LoginSessionCoordinator $sessionCoordinator = null;
    private ?LoginTokenLifecycleService $tokenLifecycleService = null;
    private ?LoginRuntimeState $runtimeState = null;
    private ?LoginGateStateService $gateStateService = null;
    private ?ApiLogin $apiLogin = null;
    private ?ApiOauth2 $apiOauth2 = null;
    private ?ApiRiskVerification $apiRiskVerification = null;
    private ?ApiMain $apiMain = null;
    private bool $notifyOnCurrentLoginFailure = false;
    private bool $runtimeReloginFailureHandled = false;

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, true);
    }

    /**
     * 执行一次任务
     * @return \Bhp\Scheduler\TaskResult
     */
    public function runOnce(): \Bhp\Scheduler\TaskResult
    {
        $this->resetTaskResult();
        $this->runtimeReloginFailureHandled = false;
        try {
            if ($this->hasPendingLoginFlow()) {
                $this->warning('检测到历史登录挂起状态，已清理并重新开始完整登录流程');
                $this->clearPendingLoginFlow();
            }

            if (!$this->hasLoginTokens()) {
                $this->initLogin();
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
            if (!$this->hasLoginTokens()) {
                throw new NoLoginException($e->getMessage());
            }
            $this->warning("登录: {$e->getMessage()}");
            return $this->retryAfter($e->getRetryAfterSeconds());
        }

        return $this->resolveTaskResult(\Bhp\Scheduler\TaskResult::after(7200));
    }

    /**
     * 判断登录Tokens是否满足条件
     * @return bool
     */
    protected function hasLoginTokens(): bool
    {
        return $this->auth('access_token') !== '' && $this->auth('refresh_token') !== '';
    }

    /**
     * 判断待处理登录流程是否满足条件
     * @return bool
     */
    protected function hasPendingLoginFlow(): bool
    {
        return $this->pendingFlowStore()->load() !== null;
    }

    /**
     * 删除或清理待处理登录流程
     * @return void
     */
    protected function clearPendingLoginFlow(): void
    {
        $this->pendingFlowStore()->clear();
    }

    /**
     * 初始化登录
     * @return void
     */
    protected function initLogin(): void
    {
        if (!$this->hasConfiguredLoginFallback()) {
            throw new NoLoginException('No login method is configured');
        }

        $this->info('启动登录程序');
        $this->info('准备载入登录令牌');
        $this->login();

        if (!$this->hasLoginTokens()) {
            throw new NoLoginException('No valid login state available');
        }

        $this->keepLogin();
    }

    /**
     * 处理登录
     * @return void
     */
    protected function login(bool $notifyOnFailure = false): void
    {
        $previousNotifyState = $this->notifyOnCurrentLoginFailure;
        $this->notifyOnCurrentLoginFailure = $notifyOnFailure;
        try {
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
        } finally {
            $this->notifyOnCurrentLoginFailure = $previousNotifyState;
        }
    }

    /**
     * 处理keep登录
     * @return void
     */
    protected function keepLogin(): void
    {
        try {
            $this->sessionCoordinator()->maintainSession(
                $this->auth('access_token'),
                $this->auth('refresh_token'),
                $this->tokenLifecycleService(),
                function (): void {
                    if (!$this->hasConfiguredLoginFallback()) {
                        $this->restartFreshLogin(
                            '运行中的登录状态已失效，且未配置可重新登录的方式',
                            true,
                        );
                    }

                    try {
                        $this->login(true);
                    } catch (NoLoginException $e) {
                        throw $e;
                    } catch (LoginException $e) {
                        $this->restartFreshLogin(
                            '运行中的登录状态已失效，重新登录失败: ' . $e->getMessage(),
                            true,
                        );
                    }

                    if (!$this->hasLoginTokens()) {
                        $this->restartFreshLogin(
                            '运行中的登录状态已失效，重新登录后仍未生成有效登录信息',
                            true,
                        );
                    }
                },
                function (): void {
                    $this->patchCookie();
                },
            );
        } catch (NoLoginException $e) {
            if ($this->runtimeReloginFailureHandled) {
                throw $e;
            }

            $this->restartFreshLogin(
                '运行中的登录状态已失效，需要重新登录: ' . $e->getMessage(),
                true,
            );
        }
    }

    /**
     * 判断Configured登录Fallback是否满足条件
     * @return bool
     */
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

    /**
     * 断言登录Ready
     * @param string $message
     * @return void
     */
    protected function assertLoginReady(string $message): void
    {
        if (!$this->gateStateService()->authReady()) {
            throw new NoLoginException($message);
        }
    }

    /**
     * 处理begin验证码登录
     * @param string $targetUrl
     * @return void
     */
    protected function beginCaptchaLogin(string $targetUrl): void
    {
        $captcha = $this->waitForManualCaptchaResolution(
            $targetUrl,
            '账密登录需要人工完成行为验证码，本次运行将等待人工处理结果，不会保留到下次运行',
        );
        $this->accountLogin($captcha['validate'], $captcha['challenge'], '账密模式(行为验证码)');
    }

    /**
     * 处理begin短信验证码登录
     * @param string $phone
     * @param string $cid
     * @param string $targetUrl
     * @return void
     */
    protected function beginSmsCaptchaLogin(string $phone, string $cid, string $targetUrl): void
    {
        $captcha = $this->waitForManualCaptchaResolution(
            $targetUrl,
            '短信登录需要人工完成行为验证码，本次运行将等待人工处理结果，不会保留到下次运行',
        );
        $recaptchaToken = $this->extractCaptchaQueryParam($targetUrl, 'recaptcha_token');
        if ($recaptchaToken === '') {
            throw new LoginException('短信登录验证码参数提取失败', 600);
        }

        $payload = $this->authenticationService()->sendSms(
            $phone,
            $cid,
            $captcha['validate'],
            $captcha['challenge'],
            $recaptchaToken,
            function (string $retryPhone, string $retryCid, string $retryTargetUrl): void {
                $this->beginSmsCaptchaLogin($retryPhone, $retryCid, $retryTargetUrl);
            },
        );
        if ($payload === null) {
            return;
        }

        $code = $this->requestSmsCodeViaBestAvailableChannel('短信验证码已发送，请在登录助手页面输入验证码后继续');
        if ($code === '') {
            throw new LoginException('短信验证码不能为空', 3600);
        }

        $response = $this->authenticationService()->submitSmsLogin($payload, $code);
        $this->completeLoginResponse('短信模式', $response);
    }

    /**
     * 等待人工完成验证码处理
     * @param string $targetUrl
     * @param string $message
     * @return array{validate:string,challenge:string}
     */
    protected function waitForManualCaptchaResolution(string $targetUrl, string $message): array
    {
        $this->warning($message);
        $this->captchaService()->assertCaptchaServiceReady();
        $captchaInfo = $this->captchaService()->matchCaptcha($targetUrl);
        if ($this->manualAssistService()->enabled()) {
            $flow = $this->manualAssistService()->openGeetestFlow(
                $captchaInfo,
                '登录行为验证码',
                $message,
            );
            $this->info('登录助手页: ' . $flow['url']);
            $this->info('请在浏览器中打开登录助手页面完成验证，当前进程会继续等待结果');
            $captcha = $this->manualAssistService()->waitForGeetestResult(
                $flow,
                function (string $progress): void {
                    $this->info($progress);
                },
            );
            $this->notice('行为验证码处理成功，继续登录流程');

            return $captcha;
        }

        $manualCaptchaUrl = $this->captchaService()->buildManualCaptchaUrl($captchaInfo);
        $expiresAt = time() + 120;
        $nextProgressLogAt = time() + 10;

        $this->info('验证码处理页: ' . $manualCaptchaUrl);
        $this->info('请在浏览器中打开验证码链接，完成验证后当前进程会继续执行，最长等待 120 秒');
        while (true) {
            try {
                $result = $this->captchaService()->pollCaptchaResult($captchaInfo['challenge'], $expiresAt);
            } catch (LoginException $e) {
                if ($e->getMessage() === '验证码识别超时') {
                    throw new LoginException('行为验证码等待超时，本次运行结束，请重新开始登录流程', 300);
                }

                throw $e;
            }

            if ($result['state'] === 'resolved') {
                $this->notice('行为验证码处理成功，继续登录流程');
                return $result['data'];
            }

            if ($result['state'] !== 'pending') {
                $detail = trim((string)($result['message'] ?? ''));
                throw new LoginException(
                    $detail !== '' ? '行为验证码处理失败: ' . $detail : '行为验证码处理失败',
                    300,
                );
            }

            $now = time();
            if ($now >= $nextProgressLogAt) {
                $remainingSeconds = max(0, $expiresAt - $now);
                $this->info(sprintf('仍在等待人工完成行为验证码，剩余约 %d 秒', $remainingSeconds));
                $nextProgressLogAt = $now + 10;
            }

            usleep(2_000_000);
        }
    }

    /**
     * 提取验证码链接参数
     * @param string $targetUrl
     * @param string $key
     * @return string
     */
    protected function extractCaptchaQueryParam(string $targetUrl, string $key): string
    {
        $query = parse_url($targetUrl, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $params);
        $value = $params[$key] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * 处理修补Cookie
     * @return void
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

    /**
     * 处理Cookie修补服务
     * @return LoginCookiePatchService
     */
    protected function cookiePatchService(): LoginCookiePatchService
    {
        return $this->cookiePatchService ??= new LoginCookiePatchService();
    }

    /**
     * 处理CookieMaintenance服务
     * @return LoginCookieMaintenanceService
     */
    protected function cookieMaintenanceService(): LoginCookieMaintenanceService
    {
        return $this->cookieMaintenanceService ??= new LoginCookieMaintenanceService($this->cookiePatchService());
    }

    /**
     * 处理令牌生命周期服务
     * @return LoginTokenLifecycleService
     */
    protected function tokenLifecycleService(): LoginTokenLifecycleService
    {
        return $this->tokenLifecycleService ??= new LoginTokenLifecycleService(
            $this->appContext(),
            $this->cookiePatchService(),
            $this->apiOauth2(),
        );
    }

    /**
     * 处理验证码服务
     * @return LoginCaptchaService
     */
    protected function captchaService(): LoginCaptchaService
    {
        return $this->captchaService ??= new LoginCaptchaService($this->appContext());
    }

    /**
     * 处理凭证服务
     * @return LoginCredentialService
     */
    protected function credentialService(): LoginCredentialService
    {
        return $this->credentialService ??= new LoginCredentialService($this->appContext(), $this->apiOauth2());
    }

    /**
     * 处理prompt服务
     * @return LoginPromptService
     */
    protected function promptService(): LoginPromptService
    {
        return $this->promptService ??= new LoginPromptService();
    }

    /**
     * 处理响应服务
     * @return LoginResponseService
     */
    protected function responseService(): LoginResponseService
    {
        return $this->responseService ??= new LoginResponseService();
    }

    /**
     * 处理决策Applier服务
     * @return LoginDecisionApplierService
     */
    protected function decisionApplierService(): LoginDecisionApplierService
    {
        return $this->decisionApplierService ??= new LoginDecisionApplierService();
    }

    /**
     * 处理模式Executor
     * @return LoginModeExecutor
     */
    protected function modeExecutor(): LoginModeExecutor
    {
        return $this->modeExecutor ??= new LoginModeExecutor();
    }

    /**
     * 处理authentication服务
     * @return LoginAuthenticationService
     */
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

    /**
     * 处理短信服务
     * @return LoginSmsService
     */
    protected function smsService(): LoginSmsService
    {
        return $this->smsService ??= new LoginSmsService($this->appContext(), $this->apiLogin());
    }

    /**
     * 处理风控验证服务
     * @return LoginRiskVerificationService
     */
    protected function riskVerificationService(): LoginRiskVerificationService
    {
        return $this->riskVerificationService ??= new LoginRiskVerificationService(
            $this->captchaService(),
            $this->apiRiskVerification(),
        );
    }

    /**
     * 处理待处理流程存储
     * @return LoginPendingFlowStore
     */
    protected function pendingFlowStore(): LoginPendingFlowStore
    {
        return $this->pendingFlowStore ??= new LoginPendingFlowStore($this->cache());
    }

    /**
     * 处理登录助手服务
     * @return LoginManualAssistService
     */
    protected function manualAssistService(): LoginManualAssistService
    {
        return $this->manualAssistService ??= new LoginManualAssistService(
            $this->captchaService(),
            $this->pendingFlowStore(),
            new ApiCaptcha($this->appContext()->request()),
        );
    }

    /**
     * 处理会话Coordinator
     * @return LoginSessionCoordinator
     */
    protected function sessionCoordinator(): LoginSessionCoordinator
    {
        return $this->sessionCoordinator ??= new LoginSessionCoordinator($this->flowController());
    }

    /**
     * 处理流程Controller
     * @return LoginFlowController
     */
    protected function flowController(): LoginFlowController
    {
        return $this->flowController ??= new LoginFlowController();
    }

    /**
     * 处理闸门状态服务
     * @return LoginGateStateService
     */
    protected function gateStateService(): LoginGateStateService
    {
        return $this->gateStateService ??= new LoginGateStateService($this->appContext());
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

    /**
     * 处理display键For日志
     * @param string $key
     * @param bool $hide
     * @return string
     */
    protected function displayKeyForLog(string $key, bool $hide): string
    {
        if ($hide && $key === 'pc_cookie') {
            return 'pc_cookie(masked)';
        }

        return $key;
    }

    /**
     * 格式化日志值For日志
     * @param string $key
     * @param mixed $value
     * @param bool $hide
     * @return string
     */
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

    /**
     * 处理maskCookie值Pairs
     * @param string $cookie
     * @return string
     */
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
                $masked[] = $segment . '=***';
                continue;
            }

            [$name] = explode('=', $segment, 2);
            $masked[] = trim($name) . '=***';
        }

        return $masked === [] ? '***' : implode('; ', $masked);
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
                function (array $riskResponse, string $message): void {
                    $this->warning($message);
                    $oauthResponse = $this->riskVerificationService()->handle(
                        $riskResponse,
                        fn (string $prompt, string $maskedPhone): string => $this->requestRiskSmsCodeViaBestAvailableChannel($prompt, $maskedPhone),
                        function (string $info): void {
                            $this->info($info);
                        },
                        function (string $warning): void {
                            $this->warning($warning);
                        },
                        function (string $notice): void {
                            $this->notice($notice);
                        },
                    );

                    $decision = $this->responseService()->decide('账密模式(安全验证)', $oauthResponse);
                    if (!$decision->isSuccess()) {
                        throw new LoginException($decision->message, $decision->retryAfterSeconds);
                    }

                    $this->info($decision->message);
                    $this->updateLoginInfo($oauthResponse);
                    $this->info('生成信息配置完毕');
                },
            );
        });
    }

    /**
     * 处理handle验证码Challenge
     * @param string $captchaUrl
     * @param string $message
     * @return void
     */
    protected function handleCaptchaChallenge(string $captchaUrl, string $message): void
    {
        $this->warning($message);
        $this->beginCaptchaLogin($captchaUrl);
    }

    /**
     * 清理当前登录状态，并要求下次重新开始完整登录流程
     * @param string $message
     * @param bool $notifyRemote
     * @return never
     */
    protected function restartFreshLogin(string $message, bool $notifyRemote): never
    {
        $this->runtimeReloginFailureHandled = $notifyRemote;
        $this->clearPendingLoginFlow();
        $this->invalidateSessionAuth();
        $this->warning($message);

        if ($notifyRemote) {
            $this->notify('login_relogin_required', $message);
        }

        throw new NoLoginException($message);
    }

    /**
     * 处理account登录
     * @param string $validate
     * @param string $challenge
     * @param string $mode
     * @return void
     */
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
            fn (string $message): string => $this->requestSmsCodeViaBestAvailableChannel($message),
            function (array $payload, string $code) use ($mode): void {
                $response = $this->authenticationService()->submitSmsLogin($payload, $code);
                $this->completeLoginResponse($mode, $response);
            },
        );
    }

    /**
     * 检查登录
     * @param int $mode_id
     * @return void
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

    protected function requestSmsCodeViaBestAvailableChannel(string $message): string
    {
        if (!$this->manualAssistService()->enabled()) {
            return $this->cliInput($message);
        }

        $flow = $this->manualAssistService()->openSmsCodeFlow(
            '登录短信验证码',
            $message,
        );
        $this->info('登录助手页: ' . $flow['url']);
        $this->info('请在浏览器中打开登录助手页面输入短信验证码，当前进程会继续等待结果');

        return $this->manualAssistService()->waitForCodeResult(
            $flow,
            function (string $progress): void {
                $this->info($progress);
            },
        );
    }

    protected function requestRiskSmsCodeViaBestAvailableChannel(string $message, string $maskedPhone = ''): string
    {
        if (!$this->manualAssistService()->enabled()) {
            return $this->cliInput($message);
        }

        $flow = $this->manualAssistService()->openRiskSmsCodeFlow(
            '安全验证短信验证码',
            $message,
            $maskedPhone,
        );
        $this->info('登录助手页: ' . $flow['url']);
        $this->info('请在浏览器中打开登录助手页面输入安全验证短信验证码，当前进程会继续等待结果');

        return $this->manualAssistService()->waitForCodeResult(
            $flow,
            function (string $progress): void {
                $this->info($progress);
            },
        );
    }

    /**
     * 获取PcCookie值
     * @return string
     */
    protected function getPcCookieValue(): string
    {
        return $this->auth('pc_cookie');
    }

    /**
     * 获取HomeHeaders
     * @return array
     */
    protected function fetchHomeHeaders(): array
    {
        $response = $this->apiMain()->home();
        return is_array($response) ? $response : [];
    }

    /**
     * 处理状态
     * @return LoginRuntimeState
     */
    protected function state(): LoginRuntimeState
    {
        return $this->runtimeState ??= new LoginRuntimeState(
            (string)($this->username ?? ''),
            (string)($this->password ?? ''),
        );
    }

    /**
     * 处理风控验证Api
     * @return ApiRiskVerification
     */
    protected function apiRiskVerification(): ApiRiskVerification
    {
        return $this->apiRiskVerification ??= new ApiRiskVerification($this->request());
    }

    /**
     * 处理invalidate会话认证
     * @return void
     */
    protected function invalidateSessionAuth(): void
    {
        foreach (['access_token', 'refresh_token', 'cookie', 'pc_cookie', 'uid', 'csrf', 'sid'] as $key) {
            $this->setAuth($key, '');
        }
    }

    /**
     * 处理同步运行时状态
     * @return void
     */
    protected function syncRuntimeState(): void
    {
        $this->username = $this->state()->username();
        $this->password = $this->state()->password();
    }

    /**
     * 处理API登录
     * @return ApiLogin
     */
    private function apiLogin(): ApiLogin
    {
        return $this->apiLogin ??= new ApiLogin($this->appContext()->request());
    }

    /**
     * 处理APIOauth2
     * @return ApiOauth2
     */
    private function apiOauth2(): ApiOauth2
    {
        return $this->apiOauth2 ??= new ApiOauth2($this->appContext()->request());
    }

    /**
     * 处理APIMain
     * @return ApiMain
     */
    private function apiMain(): ApiMain
    {
        return $this->apiMain ??= new ApiMain($this->appContext()->request());
    }

}

