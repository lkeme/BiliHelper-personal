<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Gateway;

use Bhp\Plugin\ActivityLottery\Internal\EraLiveWatchService;

final class WatchLiveGateway
{
    /**
     * @var callable(array<int, string>, int, int): ?array<string, mixed>
     */
    private readonly mixed $startAction;
    /**
     * @var callable(array<string, mixed>): array<string, mixed>
     */
    private readonly mixed $heartbeatAction;

    public function __construct(
        ?callable $startAction = null,
        ?callable $heartbeatAction = null,
    ) {
        $watchService = new EraLiveWatchService();
        $this->startAction = $startAction ?? fn (array $roomIds, int $areaId = 0, int $parentAreaId = 0): ?array => $watchService->start($roomIds, $areaId, $parentAreaId);
        $this->heartbeatAction = $heartbeatAction ?? fn (array $session): array => $watchService->heartbeat($session);
    }

    /**
     * @param string[] $roomIds
     * @return array<string, mixed>|null
     */
    public function start(array $roomIds, int $areaId = 0, int $parentAreaId = 0): ?array
    {
        $session = ($this->startAction)($roomIds, $areaId, $parentAreaId);
        return is_array($session) ? $session : null;
    }

    /**
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    public function heartbeat(array $session): array
    {
        $nextSession = ($this->heartbeatAction)($session);
        return is_array($nextSession) ? $nextSession : [];
    }
}
