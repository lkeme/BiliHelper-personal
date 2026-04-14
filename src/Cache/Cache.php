<?php declare(strict_types=1);

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

namespace Bhp\Cache;

use Bhp\Profile\ProfileContext;
use Bhp\Util\AppTerminator;

class Cache
{
    /**
     * @var array<string, true>
     */
    protected array $initializedScopes = [];

    protected ?CacheStoreInterface $store = null;
    private readonly string $databasePath;

    /**
     * 初始化 Cache
     * @param ProfileContext $profileContext
     */
    public function __construct(ProfileContext $profileContext)
    {
        $this->databasePath = $profileContext->cachePath() . 'cache.sqlite3';
        $this->store = new SqliteCacheStore($this->databasePath);
    }

    /**
     * 初始化ializeScope
     * @param string $classname
     * @return void
     */
    public function initializeScope(?string $classname = null): void
    {
        $scope = $this->resolveScope($classname);
        if (isset($this->initializedScopes[$scope])) {
            return;
        }

        $this->initializedScopes[$scope] = true;
    }

    /**
     * 处理put
     * @param string $key
     * @param mixed $value
     * @param string $classname
     * @return void
     */
    public function put(string $key, mixed $value, ?string $classname = null): void
    {
        $scope = $this->ensureScopeInitialized($classname);
        $this->store()->set($scope, $key, $value);
    }

    /**
     * 处理pull
     * @param string $key
     * @param string $classname
     * @return mixed
     */
    public function pull(string $key, ?string $classname = null): mixed
    {
        $scope = $this->ensureScopeInitialized($classname);

        return $this->store()->get($scope, $key);
    }

    /**
     * 处理flush
     * @return void
     */
    public function flush(): void
    {
        $this->initializedScopes = [];
        $this->store()->clear();
    }

    /**
     * 处理ensureScopeInitialized
     * @param string $classname
     * @return string
     */
    protected function ensureScopeInitialized(?string $classname = null): string
    {
        $scope = $this->resolveScope($classname);
        $this->initializeScope($classname);

        return $scope;
    }

    /**
     * 解析Scope
     * @param string $classname
     * @return string
     */
    protected function resolveScope(?string $classname = null): string
    {
        $scope = trim((string)($classname ?? $this->getCallClassName()));
        if ($scope === '') {
            AppTerminator::fail('缓存 scope 不能为空');
        }

        if (preg_match('/[\x7f-\xff]/', $scope)) {
            $scope = 'scope_' . substr(sha1($scope), 0, 16);
        }

        return $this->removeSpecStr($scope);
    }

    /**
     * 处理存储
     * @return CacheStoreInterface
     */
    protected function store(): CacheStoreInterface
    {
        return $this->store ??= new SqliteCacheStore($this->databasePath);
    }

    /**
     * 获取CallClass名称
     * @return string
     */
    protected function getCallClassName(): string
    {
        $backtraces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $temp = pathinfo(basename($backtraces[1]['file'] ?? ''))['filename'] ?? '';

        if ($temp === basename(str_replace('\\', '/', __CLASS__))) {
            return pathinfo(basename($backtraces[2]['file'] ?? ''))['filename'] ?? '';
        }

        return $temp;
    }

    /**
     * 删除或清理SpecStr
     * @param string $str
     * @return string
     */
    protected function removeSpecStr(string $str): string
    {
        $specs = str_split("-.,:;'*?~`!@#$%^&+=)(<>{}]|\\/、");
        foreach ($specs as $spec) {
            $str = str_replace($spec, '', $str);
        }

        return $str;
    }
}
