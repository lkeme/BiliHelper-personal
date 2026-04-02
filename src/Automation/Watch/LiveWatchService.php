<?php declare(strict_types=1);

namespace Bhp\Automation\Watch;

final class LiveWatchService
{
    private ?\Closure $startHandler;
    private ?\Closure $heartbeatHandler;

    /**
     * @param null|callable(int, array<string, mixed>):LiveWatchSession $startHandler
     * @param null|callable(LiveWatchSession):LiveWatchSession $heartbeatHandler
     */
    public function __construct(
        ?callable $startHandler = null,
        ?callable $heartbeatHandler = null,
    ) {
        $this->startHandler = $startHandler !== null ? \Closure::fromCallable($startHandler) : null;
        $this->heartbeatHandler = $heartbeatHandler !== null ? \Closure::fromCallable($heartbeatHandler) : null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function start(int $roomId, array $context = []): LiveWatchSession
    {
        if ($this->startHandler !== null) {
            return ($this->startHandler)($roomId, $context);
        }

        return LiveWatchSession::start($roomId, $context);
    }

    public function heartbeat(LiveWatchSession $session): LiveWatchSession
    {
        if ($this->heartbeatHandler !== null) {
            return ($this->heartbeatHandler)($session);
        }

        return $session->with([
            'seq_id' => $session->seqId + 1,
            'last_heartbeat_at' => microtime(true),
        ]);
    }
}
