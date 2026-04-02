<?php declare(strict_types=1);

namespace Bhp\Automation\Watch;

final class VideoWatchService
{
    private ?\Closure $startHandler;
    private ?\Closure $finishHandler;

    /**
     * @param null|callable(VideoWatchSession):bool $startHandler
     * @param null|callable(VideoWatchSession, int):bool $finishHandler
     */
    public function __construct(
        ?callable $startHandler = null,
        ?callable $finishHandler = null,
    ) {
        $this->startHandler = $startHandler !== null ? \Closure::fromCallable($startHandler) : null;
        $this->finishHandler = $finishHandler !== null ? \Closure::fromCallable($finishHandler) : null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function start(string $archiveId, string $cid, array $context = []): VideoWatchSession
    {
        $session = VideoWatchSession::start($archiveId, $cid, $context);
        if ($this->startHandler !== null && !($this->startHandler)($session)) {
            throw new \RuntimeException('视频观看初始化失败');
        }

        return $session;
    }

    public function finish(VideoWatchSession $session, int $watchedSeconds): bool
    {
        if ($watchedSeconds <= 0) {
            return false;
        }

        if ($this->finishHandler === null) {
            return true;
        }

        return (bool)($this->finishHandler)($session, $watchedSeconds);
    }
}
