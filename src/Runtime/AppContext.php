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

    public function appRoot(): string
    {
        return $this->profileContext()->appRoot();
    }

    public function profileName(): string
    {
        return $this->profileContext()->name();
    }

    public function httpClient(): HttpClient
    {
        return $this->httpClient ??= $this->service(HttpClient::class);
    }

    public function log(): Log
    {
        return $this->logService ??= $this->service(Log::class);
    }

    public function request(): Request
    {
        return $this->requestService ??= $this->service(Request::class);
    }

    public function cache(): Cache
    {
        return $this->cacheService();
    }

    public function config(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return $this->configService()->get($key, $default, $type);
    }

    public function enabled(string $key, bool $default = false): bool
    {
        return (bool)$this->config($key . '.enable', $default, 'bool');
    }

    public function device(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return $this->deviceService()->get($key, $default, $type);
    }

    public function filterWords(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return $this->filterWordsService()->get($key, $default, $type);
    }

    public function appName(): string
    {
        return $this->envService()->app_name;
    }

    public function appVersion(): string
    {
        return $this->envService()->app_version;
    }

    public function appSource(): string
    {
        return $this->envService()->app_source;
    }

    public function setConfig(string $key, mixed $value): void
    {
        $this->configService()->set($key, $value);
    }

    protected function configService(): Config
    {
        return $this->configService ??= $this->service(Config::class);
    }

    protected function cacheService(): Cache
    {
        return $this->cacheService ??= $this->service(Cache::class);
    }

    protected function deviceService(): Device
    {
        return $this->deviceService ??= $this->service(Device::class);
    }

    protected function filterWordsService(): FilterWords
    {
        return $this->filterWordsService ??= $this->service(FilterWords::class);
    }

    protected function envService(): Env
    {
        return $this->envService ??= $this->service(Env::class);
    }

    public function userProfileService(): UserProfileService
    {
        return $this->userProfileService ??= new UserProfileService(
            $this->log(),
            new \Bhp\Api\Vip\ApiUser($this->request()),
        );
    }

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

    public function csrf(): string
    {
        return $this->authCookieField('/(?:^|;\s*)bili_jct=([^;]{32})/');
    }

    public function uid(): string
    {
        return $this->authCookieField('/(?:^|;\s*)DedeUserID=(\d+)/');
    }

    public function sid(): string
    {
        return $this->authCookieField('/(?:^|;\s*)DedeUserID__ckMd5=([^;]{16})/');
    }

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

    public function profileContext(): ProfileContext
    {
        return $this->profileContext;
    }

    public function profileRoot(): string
    {
        return $this->profileContext()->rootPath();
    }

    public function configPath(): string
    {
        return $this->profileContext()->configPath();
    }

    public function logPath(): string
    {
        return $this->profileContext()->logPath();
    }

    public function cachePath(): string
    {
        return $this->profileContext()->cachePath();
    }

    protected function service(string $id): mixed
    {
        return $this->services->get($id);
    }

    private function authCookieField(string $pattern): string
    {
        preg_match($pattern, $this->auth('cookie'), $matches);

        return (string)($matches[1] ?? '');
    }
}
