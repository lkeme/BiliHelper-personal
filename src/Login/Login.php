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
use Bhp\Api\Response\LoginDecision;
use Bhp\Api\Response\LoginTokenBundle;
use Bhp\Api\Response\QrAuthCode;
use Bhp\Api\WWW\ApiMain;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Runtime\Runtime;
use Bhp\Util\Common\Common;
use Bhp\Util\Exceptions\LoginException;
use Bhp\Util\Exceptions\NoLoginException;
use Bhp\Util\Exceptions\RequestException;
use Bhp\Util\Qrcode\Qrcode;

class Login extends BasePlugin implements PluginTaskInterface
{
    /**
     * 插件信息
     * @var array<string, mixed>|null
     */
    public ?array $info = [
        'hook' => 'Login', // hook
        'name' => 'Login', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '登录', // 插件描述
        'author' => 'Lkeme', // 作者
        'priority' => 1001, // 插件优先级
        'cycle' => '2(小时)', // 运行周期
        'interval_seconds' => 7200,
        'max_concurrency' => 1,
        'overrun_policy' => 'serialize',
        'timeout_seconds' => 180.0,
        'bootstrap_first' => true,
    ];

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
    private ?LoginQrCoordinator $qrCoordinator = null;
    private ?LoginDecisionApplierService $decisionApplierService = null;
    private ?LoginModeExecutor $modeExecutor = null;
    private ?LoginPromptService $promptService = null;
    private ?LoginQrService $qrService = null;
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

                $this->assertLoginReady('未获取到有效登录状态');
                return $this->resolveTaskResult(\Bhp\Scheduler\TaskResult::after(7200));
            }

            $this->keepLogin();
        } catch (RequestException $e) {
            return $this->retryAfterRequestException($e, '登录', 10 * 60);
        } catch (LoginException $e) {
            if ($this->hasPendingLoginFlow()) {
                Log::warning("登录: {$e->getMessage()}");
                return $this->retryAfter($e->getRetryAfterSeconds());
            }

            if (!$this->hasLoginTokens()) {
                throw new NoLoginException($e->getMessage());
            }
            Log::warning("登录: {$e->getMessage()}");
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
            throw new NoLoginException('未配置可用登录方式');
        }

        Log::info('启动登录程序');
        Log::info('准备载入登录令牌');
        $this->login();
        if ($this->hasPendingLoginFlow()) {
            return;
        }

        if (!$this->hasLoginTokens()) {
            throw new NoLoginException('未获取到有效登录状态');
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
                    throw new LoginException('登录流程待完成', 2);
                }

                if (!$this->hasLoginTokens()) {
                    throw new NoLoginException('未获取到有效登录状态');
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
        if ($this->hasPendingLoginFlow() || !$this->hasLoginTokens()) {
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
                    Log::warning("此次请求需要行为验证码");
                    $this->beginSmsCaptchaLogin($phone, $cid, $targetUrl);
                },
            ),
            fn (string $message): string => $this->cliInput($message),
            function (array $payload, string $code): void {
                $response = ApiLogin::smsLogin($payload, $code);
                $this->authenticationService()->completeLogin('短信模式', $response, function (LoginDecision $decision, array $rawResponse): void {
                    $this->decisionApplierService()->apply(
                        $decision,
                        $rawResponse,
                        function (array $successResponse, string $message): void {
                            Log::info($message);
                            $this->updateLoginInfo($successResponse);
                            Log::info('生成信息配置完毕');
                        },
                        function (string $captchaUrl, string $message): void {
                            Log::warning($message);
                            $this->beginCaptchaLogin($captchaUrl);
                        },
                    );
                });
            },
            function (string $authCode): bool {
                $result = $this->qrCoordinator()->pollAuthCode($authCode, function (array $response): void {
                    $this->updateLoginInfo($response);
                });
                if (!$result['confirmed']) {
                    Log::info("等待扫码 {$result['message']}");
                    return false;
                }

                Log::notice("扫码成功 {$result['message']}");

                return true;
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
        Log::info('请在浏览器中打开以下链接，完成验证码识别');
        Log::info($result['display_url']);
        Log::info('请在2分钟内完成识别操作');
        $this->scheduleAfter($result['delay_seconds']);
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
        Log::info('请在浏览器中打开以下链接，完成验证码识别');
        Log::info($result['display_url']);
        Log::info('请在2分钟内完成识别操作');
        $this->scheduleAfter($result['delay_seconds']);
    }

    protected function beginQrcodeLoginPolling(QrAuthCode $qrData): void
    {
        $this->invalidateSessionAuth();
        $result = $this->pendingFlowLifecycleService()->beginQrcodePolling($this->state(), $qrData, time() + 180);
        $this->syncRuntimeState();
        Log::info("1.终端直接显示(输入:1)");
        Log::info("2.浏览器链接访问(输入:2)");
        $option = $this->cliInput("请输入二维码显示方式: ");
        $display = $this->qrCoordinator()->resolveDisplay($option, $result['qr_url']);
        if ($display['mode'] === 'terminal') {
            $this->cliInput("请尝试放大窗口，以确保二维码完整显示，回车继续");
            Qrcode::show($display['url']);
        } else {
            Log::info("请使用浏览器访问下面的链接，以确保二维码完整显示");
            Log::info($display['url']);
        }
        $this->scheduleAfter($result['delay_seconds']);
    }

    /**
     * @return array<string, string>|null
     */
    protected function fetchCaptchaResult(string $challenge): ?array
    {
        $response = $this->pendingFlowLifecycleService()->fetchCaptchaResult($this->state(), $challenge);
        if ($response !== null) {
            Log::notice('验证码识别成功');
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
            'notice' => Log::notice($result['message']),
            'warning' => Log::warning($result['message']),
            default => Log::info($result['message']),
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
            Runtime::getInstance()->appContext(),
            $this->cookiePatchService(),
        );
    }

    protected function captchaService(): LoginCaptchaService
    {
        return $this->captchaService ??= new LoginCaptchaService(Runtime::getInstance()->appContext());
    }

    protected function credentialService(): LoginCredentialService
    {
        return $this->credentialService ??= new LoginCredentialService(Runtime::getInstance()->appContext());
    }

    protected function promptService(): LoginPromptService
    {
        return $this->promptService ??= new LoginPromptService();
    }

    protected function qrService(): LoginQrService
    {
        return $this->qrService ??= new LoginQrService();
    }

    protected function responseService(): LoginResponseService
    {
        return $this->responseService ??= new LoginResponseService();
    }

    protected function qrCoordinator(): LoginQrCoordinator
    {
        return $this->qrCoordinator ??= new LoginQrCoordinator(
            $this->qrService(),
            $this->promptService(),
        );
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
        );
    }

    protected function smsService(): LoginSmsService
    {
        return $this->smsService ??= new LoginSmsService(Runtime::getInstance()->appContext());
    }

    protected function pendingFlowStore(): LoginPendingFlowStore
    {
        return $this->pendingFlowStore ??= new LoginPendingFlowStore();
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
        //
        // $this->updateInfo('username',$this->username);
        // $this->updateInfo('password',$this->password);
        // 转换 TODO: 扫码无效
        // $access_token = $this->tvConvert();
        // $this->updateInfo('access_token', $access_token);
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
            Log::info(" > " . $this->displayKeyForLog($key, $hide) . ': ' . $this->formatLogValueForLog($key, $value, $hide));
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
    protected function accountLogin(string $validate = '', string $challenge = '', string $mode = '账密模式'): void
    {
        Log::info("尝试 $mode 登录");
        $this->authenticationService()->accountLogin(
            $this->state(),
            $mode,
            $validate,
            $challenge,
            function (string $mode, array $response): void {
                $this->authenticationService()->completeLogin($mode, $response, function (LoginDecision $decision, array $rawResponse): void {
                    $this->decisionApplierService()->apply(
                        $decision,
                        $rawResponse,
                        function (array $successResponse, string $message): void {
                            Log::info($message);
                            $this->updateLoginInfo($successResponse);
                            Log::info('生成信息配置完毕');
                        },
                        function (string $captchaUrl, string $message): void {
                            Log::warning($message);
                            $this->beginCaptchaLogin($captchaUrl);
                        },
                    );
                });
            },
        );
    }

    /**
     * 短信登录
     * @param string $mode
     */
    protected function smsLogin(string $mode = '短信模式'): void
    {
        Log::info("尝试 $mode 登录");
        $this->authenticationService()->smsLogin(
            $this->state(),
            (string) $this->config('login_country.code'),
            (bool) $this->config('login_check.phone'),
            function (string $phone, string $countryCode, string $targetUrl): void {
                $this->beginSmsCaptchaLogin($phone, $countryCode, $targetUrl);
            },
            fn (string $message): string => $this->cliInput($message),
            function (array $payload, string $code) use ($mode): void {
                $response = ApiLogin::smsLogin($payload, $code);
                $this->authenticationService()->completeLogin($mode, $response, function (LoginDecision $decision, array $rawResponse): void {
                    $this->decisionApplierService()->apply(
                        $decision,
                        $rawResponse,
                        function (array $successResponse, string $message): void {
                            Log::info($message);
                            $this->updateLoginInfo($successResponse);
                            Log::info('生成信息配置完毕');
                        },
                        function (string $captchaUrl, string $message): void {
                            Log::warning($message);
                            $this->beginCaptchaLogin($captchaUrl);
                        },
                    );
                });
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
        $response = ApiMain::home();
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

}
