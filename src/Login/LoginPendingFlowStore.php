<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Cache\Cache;

final class LoginPendingFlowStore
{
    private const CACHE_SCOPE = 'Login';
    private const CACHE_KEY = 'pending_login_flow';

    /**
     * @return array<string, mixed>|null
     */
    public function load(): ?array
    {
        Cache::initCache(self::CACHE_SCOPE);
        $flow = Cache::get(self::CACHE_KEY, self::CACHE_SCOPE);

        return is_array($flow) ? $flow : null;
    }

    /**
     * @param array<string, mixed> $flow
     */
    public function save(array $flow): void
    {
        Cache::initCache(self::CACHE_SCOPE);
        Cache::set(self::CACHE_KEY, $flow, self::CACHE_SCOPE);
    }

    public function clear(): void
    {
        Cache::initCache(self::CACHE_SCOPE);
        Cache::set(self::CACHE_KEY, null, self::CACHE_SCOPE);
    }
}
