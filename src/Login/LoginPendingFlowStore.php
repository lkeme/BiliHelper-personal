<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Cache\Cache;

final class LoginPendingFlowStore
{
    private const CACHE_SCOPE = 'Login';
    private const CACHE_KEY = 'pending_login_flow';

    public function __construct(
        private readonly Cache $cache,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(): ?array
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $flow = $this->cache->pull(self::CACHE_KEY, self::CACHE_SCOPE);

        return is_array($flow) ? $flow : null;
    }

    /**
     * @param array<string, mixed> $flow
     */
    public function save(array $flow): void
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $this->cache->put(self::CACHE_KEY, $flow, self::CACHE_SCOPE);
    }

    public function clear(): void
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $this->cache->put(self::CACHE_KEY, null, self::CACHE_SCOPE);
    }
}
