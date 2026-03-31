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

use Bhp\Util\DesignPattern\SingleTon;
use Bhp\Util\AppTerminator;

class Cache extends SingleTon
{
    /**
     * @var array<string, true>
     */
    protected array $initializedScopes = [];

    protected ?CacheStoreInterface $store = null;

    public function init(): void
    {
        $this->store = new SqliteCacheStore(PROFILE_CACHE_PATH . 'cache.sqlite3');
    }

    public static function initCache(?string $classname = null): void
    {
        $scope = self::getInstance()->resolveScope($classname);
        if (isset(self::getInstance()->initializedScopes[$scope])) {
            return;
        }

        self::getInstance()->initializedScopes[$scope] = true;
    }

    public static function set(string $key, mixed $value, ?string $classname = null): void
    {
        $scope = self::getInstance()->ensureScopeInitialized($classname);
        self::getInstance()->store()->set($scope, $key, $value);
    }

    public static function get(string $key, ?string $classname = null): mixed
    {
        $scope = self::getInstance()->ensureScopeInitialized($classname);

        return self::getInstance()->store()->get($scope, $key);
    }

    public static function clearAll(): void
    {
        self::getInstance()->initializedScopes = [];
        self::getInstance()->store()->clear();
    }

    protected function ensureScopeInitialized(?string $classname = null): string
    {
        $scope = $this->resolveScope($classname);
        self::initCache($classname);

        return $scope;
    }

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

    protected function store(): CacheStoreInterface
    {
        return $this->store ??= new SqliteCacheStore(PROFILE_CACHE_PATH . 'cache.sqlite3');
    }

    protected function getCallClassName(): string
    {
        $backtraces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $temp = pathinfo(basename($backtraces[1]['file'] ?? ''))['filename'] ?? '';

        if ($temp === basename(str_replace('\\', '/', __CLASS__))) {
            return pathinfo(basename($backtraces[2]['file'] ?? ''))['filename'] ?? '';
        }

        return $temp;
    }

    protected function removeSpecStr(string $str): string
    {
        $specs = str_split("-.,:;'*?~`!@#$%^&+=)(<>{}]|\\/、");
        foreach ($specs as $spec) {
            $str = str_replace($spec, '', $str);
        }

        return $str;
    }
}
