<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\State;

use Bhp\Cache\Cache;

/**
 * 插件私有状态存储。
 *
 * 走项目 Cache（SQLite），显式 scope；所有 key 以账号 uid 前缀隔离，避免多 profile 共库串号。
 * 关注/取关队列状态不在此处，见 Bhp\Automation\Follow\*。
 */
final class CarnivalStateStore
{
    public const SCOPE = 'EraSummerCarnival';

    private const KEY_SNAPSHOT = 'snapshot';
    private const KEY_SIGNIN_DATE = 'signin_date';
    private const KEY_CLAIMED_SIDS = 'claimed_sids';
    private const KEY_WATCH_PROGRESS = 'watch_progress';
    private const KEY_WATCH_SESSION = 'watch_session';
    private const KEY_DRAW_STATS = 'draw_stats';

    private readonly string $accountKey;

    public function __construct(
        private readonly Cache $cache,
        string $accountKey,
    ) {
        $this->accountKey = trim($accountKey) !== '' ? trim($accountKey) : 'anonymous';
        $this->cache->initializeScope(self::SCOPE);
    }

    /**
     * 读取活动配置快照
     * @return array<string, mixed>|null
     */
    public function snapshot(): ?array
    {
        $value = $this->read(self::KEY_SNAPSHOT);

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function putSnapshot(array $snapshot): void
    {
        $this->write(self::KEY_SNAPSHOT, $snapshot);
    }

    /**
     * 当日是否已签到（本地兜底判据）
     */
    public function signedInOn(string $date): bool
    {
        return trim((string)$this->read(self::KEY_SIGNIN_DATE)) === trim($date);
    }

    public function markSignedIn(string $date): void
    {
        $this->write(self::KEY_SIGNIN_DATE, trim($date));
    }

    /**
     * 已领取的 checkpoint sid
     * @return string[]
     */
    public function claimedSids(): array
    {
        $value = $this->read(self::KEY_CLAIMED_SIDS);
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $sid): string => trim((string)$sid), $value),
            static fn (string $sid): bool => $sid !== '',
        )));
    }

    public function isClaimed(string $sid): bool
    {
        return in_array(trim($sid), $this->claimedSids(), true);
    }

    public function markClaimed(string $sid): void
    {
        $sid = trim($sid);
        if ($sid === '') {
            return;
        }

        $claimed = $this->claimedSids();
        if (in_array($sid, $claimed, true)) {
            return;
        }

        $claimed[] = $sid;
        $this->write(self::KEY_CLAIMED_SIDS, $claimed);
    }

    /**
     * 当日观看进度（秒）
     */
    public function watchedSeconds(string $date): int
    {
        $value = $this->read(self::KEY_WATCH_PROGRESS);
        if (!is_array($value) || trim((string)($value['date'] ?? '')) !== trim($date)) {
            return 0;
        }

        return max(0, (int)($value['seconds'] ?? 0));
    }

    public function addWatchedSeconds(string $date, int $seconds): int
    {
        $total = $this->watchedSeconds($date) + max(0, $seconds);
        $this->write(self::KEY_WATCH_PROGRESS, ['date' => trim($date), 'seconds' => $total]);

        return $total;
    }

    /**
     * 跨 tick 观看会话
     * @return array<string, mixed>|null
     */
    public function watchSession(): ?array
    {
        $value = $this->read(self::KEY_WATCH_SESSION);

        return is_array($value) && $value !== [] ? $value : null;
    }

    /**
     * @param array<string, mixed> $session
     */
    public function putWatchSession(array $session): void
    {
        $this->write(self::KEY_WATCH_SESSION, $session);
    }

    public function clearWatchSession(): void
    {
        $this->write(self::KEY_WATCH_SESSION, []);
    }

    /**
     * 当日抽奖次数
     */
    public function drawCount(string $date): int
    {
        $value = $this->read(self::KEY_DRAW_STATS);
        if (!is_array($value) || trim((string)($value['date'] ?? '')) !== trim($date)) {
            return 0;
        }

        return max(0, (int)($value['count'] ?? 0));
    }

    public function addDrawCount(string $date, int $delta = 1): int
    {
        $total = $this->drawCount($date) + max(0, $delta);
        $this->write(self::KEY_DRAW_STATS, ['date' => trim($date), 'count' => $total]);

        return $total;
    }

    /**
     * 处理read
     * @param string $key
     * @return mixed
     */
    private function read(string $key): mixed
    {
        $value = $this->cache->pull($this->namespacedKey($key), self::SCOPE);

        return $value === false ? null : $value;
    }

    /**
     * 处理write
     * @param string $key
     * @param mixed $value
     * @return void
     */
    private function write(string $key, mixed $value): void
    {
        $this->cache->put($this->namespacedKey($key), $value, self::SCOPE);
    }

    /**
     * 处理namespaced键
     * @param string $key
     * @return string
     */
    private function namespacedKey(string $key): string
    {
        return $this->accountKey . '_' . $key;
    }
}
