<?php declare(strict_types=1);

namespace Bhp\Automation\Watch;

final class LiveWatchSession
{
    /**
     * @param int[] $secretRule
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly int $roomId,
        public readonly int $ruid = 0,
        public readonly int $parentAreaId = 0,
        public readonly int $areaId = 0,
        public readonly int $seqId = 0,
        public readonly int $heartbeatInterval = 60,
        public readonly int $ets = 0,
        public readonly string $secretKey = '',
        public readonly array $secretRule = [],
        public readonly float $lastHeartbeatAt = 0.0,
        public readonly string $liveBuvid = '',
        public readonly string $liveUuid = '',
        public readonly string $roomTitle = '',
        public readonly string $roomUname = '',
        public readonly string $roomPickSource = 'room',
        public readonly array $extra = [],
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function start(int $roomId, array $context = []): self
    {
        $knownKeys = [
            'room_id',
            'ruid',
            'parent_area_id',
            'area_id',
            'seq_id',
            'heartbeat_interval',
            'ets',
            'secret_key',
            'secret_rule',
            'last_heartbeat_at',
            'live_buvid',
            'live_uuid',
            'room_title',
            'room_uname',
            'room_pick_source',
            'extra',
        ];
        $extra = is_array($context['extra'] ?? null) ? $context['extra'] : [];
        foreach ($context as $key => $value) {
            if (!in_array((string)$key, $knownKeys, true)) {
                $extra[(string)$key] = $value;
            }
        }

        return new self(
            roomId: $roomId > 0 ? $roomId : (int)($context['room_id'] ?? 0),
            ruid: (int)($context['ruid'] ?? 0),
            parentAreaId: (int)($context['parent_area_id'] ?? 0),
            areaId: (int)($context['area_id'] ?? 0),
            seqId: (int)($context['seq_id'] ?? 0),
            heartbeatInterval: max(30, (int)($context['heartbeat_interval'] ?? 60)),
            ets: (int)($context['ets'] ?? 0),
            secretKey: trim((string)($context['secret_key'] ?? '')),
            secretRule: array_values(array_map(static fn (mixed $rule): int => (int)$rule, (array)($context['secret_rule'] ?? []))),
            lastHeartbeatAt: max(0.0, (float)($context['last_heartbeat_at'] ?? 0.0)),
            liveBuvid: trim((string)($context['live_buvid'] ?? '')),
            liveUuid: trim((string)($context['live_uuid'] ?? '')),
            roomTitle: trim((string)($context['room_title'] ?? '')),
            roomUname: trim((string)($context['room_uname'] ?? '')),
            roomPickSource: trim((string)($context['room_pick_source'] ?? 'room')),
            extra: $extra,
        );
    }

    /**
     * @param array<string, mixed> $session
     */
    public static function fromArray(array $session): self
    {
        return self::start((int)($session['room_id'] ?? 0), $session);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function with(array $context): self
    {
        return self::start($this->roomId, array_merge($this->toArray(), $context));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge([
            'room_id' => $this->roomId,
            'ruid' => $this->ruid,
            'parent_area_id' => $this->parentAreaId,
            'area_id' => $this->areaId,
            'seq_id' => $this->seqId,
            'heartbeat_interval' => $this->heartbeatInterval,
            'ets' => $this->ets,
            'secret_key' => $this->secretKey,
            'secret_rule' => $this->secretRule,
            'last_heartbeat_at' => $this->lastHeartbeatAt,
            'live_buvid' => $this->liveBuvid,
            'live_uuid' => $this->liveUuid,
            'room_title' => $this->roomTitle,
            'room_uname' => $this->roomUname,
            'room_pick_source' => $this->roomPickSource,
        ], $this->extra);
    }
}
