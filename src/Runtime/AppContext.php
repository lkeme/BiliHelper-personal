<?php declare(strict_types=1);

namespace Bhp\Runtime;

use Bhp\App\ServiceContainer;
use Bhp\Cache\Cache;
use Bhp\Config\Config;
use Bhp\Device\Device;
use Bhp\Env\Env;
use Bhp\FilterWords\FilterWords;
use Bhp\Http\HttpClient;
use Bhp\Log\Log;
use Bhp\Profile\ProfileContext;
use Bhp\Request\Request;
use Bhp\User\UserProfileService;

class AppContext
{
    /**
     * @var string[]
     */
    private const AUTH_FILLABLE = [
        'username',
        'password',
        'uid',
        'csrf',
        'cookie',
        'access_token',
        'refresh_token',
        'sid',
        'pc_cookie',
    ];

    private ?ProfileContext $profileContext = null;
    private ?ServiceContainer $services = null;
    private ?Cache $cacheService = null;
    private ?Config $configService = null;
    private ?Device $deviceService = null;
    private ?FilterWords $filterWordsService = null;
    private ?Env $envService = null;
    private ?UserProfileService $userProfileService = null;
    private ?HttpClient $httpClient = null;
    private ?Log $logService = null;
    private ?Request $requestService = null;

    /**
     * 初始化 AppContext
     * @param ProfileContext $profileContext
     * @param ServiceContainer $services
     * @param Cache $cacheService
     * @param Config $configService
     * @param Device $deviceService
     * @param FilterWords $filterWordsService
     * @param Env $envService
     * @param HttpClient $httpClient
     */
    public function __construct(
        ProfileContext $profileContext,
        ServiceContainer $services,
        ?Cache $cacheService = null,
        ?Config $configService = null,
        ?Device $deviceService = null,
        ?FilterWords $filterWordsService = null,
        ?Env $envService = null,
        ?HttpClient $httpClient = null,
    ) {
        $this->profileContext = $profileContext;
        $this->services = $services;
        $this->cacheService = $cacheService;
        $this->configService = $configService;
        $this->deviceService = $deviceService;
        $this->filterWordsService = $filterWordsService;
        $this->envService = $envService;
        $this->httpClient = $httpClient;
    }

    /**
     * 处理应用Root
     * @return string
     */
    public function appRoot(): string
    {
        return $this->profileContext()->appRoot();
    }

    /**
     * 处理画像名称
     * @return string
     */
    public function profileName(): string
    {
        return $this->profileContext()->name();
    }

    /**
     * 处理HTTP客户端
     * @return HttpClient
     */
    public function httpClient(): HttpClient
    {
        return $this->httpClient ??= $this->service(HttpClient::class);
    }

    /**
     * 处理日志
     * @return Log
     */
    public function log(): Log
    {
        return $this->logService ??= $this->service(Log::class);
    }

    /**
     * 处理请求
     * @return Request
     */
    public function request(): Request
    {
        return $this->requestService ??= $this->service(Request::class);
    }

    /**
     * 处理缓存
     * @return Cache
     */
    public function cache(): Cache
    {
        return $this->cacheService();
    }

    /**
     * 处理配置
     * @param string $key
     * @param mixed $default
     * @param string $type
     * @return mixed
     */
    public function config(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return $this->configService()->get($key, $default, $type);
    }

    /**
     * 处理enabled
     * @param string $key
     * @param bool $default
     * @return bool
     */
    public function enabled(string $key, bool $default = false): bool
    {
        return (bool)$this->config($key . '.enable', $default, 'bool');
    }

    /**
     * 处理device
     * @param string $key
     * @param mixed $default
     * @param string $type
     * @return mixed
     */
    public function device(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return $this->deviceService()->get($key, $default, $type);
    }

    /**
     * 处理过滤Words
     * @param string $key
     * @param mixed $default
     * @param string $type
     * @return mixed
     */
    public function filterWords(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return $this->filterWordsService()->get($key, $default, $type);
    }

    /**
     * 处理应用名称
     * @return string
     */
    public function appName(): string
    {
        return $this->envService()->app_name;
    }

    /**
     * 处理应用Version
     * @return string
     */
    public function appVersion(): string
    {
        return $this->envService()->app_version;
    }

    /**
     * 处理应用来源
     * @return string
     */
    public function appSource(): string
    {
        return $this->envService()->app_source;
    }

    /**
     * 设置配置
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setConfig(string $key, mixed $value): void
    {
        $this->configService()->set($key, $value);
    }

    /**
     * 处理配置服务
     * @return Config
     */
    protected function configService(): Config
    {
        return $this->configService ??= $this->service(Config::class);
    }

    /**
     * 处理缓存服务
     * @return Cache
     */
    protected function cacheService(): Cache
    {
        return $this->cacheService ??= $this->service(Cache::class);
    }

    /**
     * 处理device服务
     * @return Device
     */
    protected function deviceService(): Device
    {
        return $this->deviceService ??= $this->service(Device::class);
    }

    /**
     * 处理过滤Words服务
     * @return FilterWords
     */
    protected function filterWordsService(): FilterWords
    {
        return $this->filterWordsService ??= $this->service(FilterWords::class);
    }

    /**
     * 处理env服务
     * @return Env
     */
    protected function envService(): Env
    {
        return $this->envService ??= $this->service(Env::class);
    }

    /**
     * 处理用户画像服务
     * @return UserProfileService
     */
    public function userProfileService(): UserProfileService
    {
        return $this->userProfileService ??= new UserProfileService(
            $this->log(),
            new \Bhp\Api\Vip\ApiUser($this->request()),
        );
    }

    /**
     * 处理认证
     * @param string $key
     * @return string
     */
    public function auth(string $key): string
    {
        if (!in_array($key, self::AUTH_FILLABLE, true)) {
            return '';
        }

        $this->cacheService()->initializeScope('Login');
        $value = $this->cacheService()->pull('auth_' . $key, 'Login');

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        return '';
    }

    /**
     * 处理csrf
     * @return string
     */
    public function csrf(): string
    {
        return $this->authCookieField('/(?:^|;\s*)bili_jct=([^;]{32})/');
    }

    /**
     * 处理UID
     * @return string
     */
    public function uid(): string
    {
        return $this->authCookieField('/(?:^|;\s*)DedeUserID=(\d+)/');
    }

    /**
     * 处理sid
     * @return string
     */
    public function sid(): string
    {
        return $this->authCookieField('/(?:^|;\s*)DedeUserID__ckMd5=([^;]{16})/');
    }

    /**
     * 设置认证
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setAuth(string $key, mixed $value): void
    {
        if (!in_array($key, self::AUTH_FILLABLE, true)) {
            return;
        }

        $this->cacheService()->initializeScope('Login');
        $normalized = is_string($value) ? $value : (($value === null || $value === false) ? '' : (string)$value);
        $this->cacheService()->put('auth_' . $key, $normalized, 'Login');
    }

    /**
     * @return string[]
     */
    public function authKeys(): array
    {
        return self::AUTH_FILLABLE;
    }

    /**
     * @return array<string, string>
     */
    public function authSnapshot(): array
    {
        $snapshot = [];
        foreach ($this->authKeys() as $key) {
            $value = $this->auth($key);
            if ($value === '') {
                continue;
            }

            $snapshot[$key] = $value;
        }

        return $snapshot;
    }

    /**
     * @param array<string, string> $snapshot
     */
    public function restoreAuthSnapshot(array $snapshot): void
    {
        foreach ($snapshot as $key => $value) {
            $this->setAuth($key, $value);
        }
    }

    /**
     * 处理画像上下文
     * @return ProfileContext
     */
    public function profileContext(): ProfileContext
    {
        return $this->profileContext;
    }

    /**
     * 处理画像Root
     * @return string
     */
    public function profileRoot(): string
    {
        return $this->profileContext()->rootPath();
    }

    /**
     * 处理配置Path
     * @return string
     */
    public function configPath(): string
    {
        return $this->profileContext()->configPath();
    }

    /**
     * 处理日志Path
     * @return string
     */
    public function logPath(): string
    {
        return $this->profileContext()->logPath();
    }

    /**
     * 处理缓存Path
     * @return string
     */
    public function cachePath(): string
    {
        return $this->profileContext()->cachePath();
    }

    /**
     * 处理服务
     * @param string $id
     * @return mixed
     */
    protected function service(string $id): mixed
    {
        return $this->services->get($id);
    }

    /**
     * 处理认证Cookie字段
     * @param string $pattern
     * @return string
     */
    private function authCookieField(string $pattern): string
    {
        preg_match($pattern, $this->auth('cookie'), $matches);

        return (string)($matches[1] ?? '');
    }
}
