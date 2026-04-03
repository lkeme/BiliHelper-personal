<?php declare(strict_types=1);

namespace Bhp\Automation\Watch;

use Bhp\Api\Video\ApiWatch;

final class VideoWatchService
{
    private \Closure $videoAction;
    private \Closure $heartbeatAction;

    /**
     * @param null|callable(string, string, string, array<string, mixed>):array<string, mixed> $videoAction
     * @param null|callable(string, string, int, string, array<string, mixed>):array<string, mixed> $heartbeatAction
     */
    public function __construct(
        ?callable $videoAction = null,
        ?callable $heartbeatAction = null,
    ) {
        $this->videoAction = $videoAction !== null
            ? \Closure::fromCallable($videoAction)
            : static fn (string $aid, string $cid, string $bvid, array $options): array => ApiWatch::video($aid, $cid, $bvid, $options);
        $this->heartbeatAction = $heartbeatAction !== null
            ? \Closure::fromCallable($heartbeatAction)
            : static fn (string $aid, string $cid, int $progress, string $bvid, array $options): array => ApiWatch::heartbeat($aid, $cid, $progress, $bvid, $options);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function start(string $archiveId, string $cid, array $context = []): VideoWatchSession
    {
        if (trim($archiveId) === '' || trim($cid) === '') {
            throw new \RuntimeException('视频观看参数无效');
        }

        $session = VideoWatchSession::start($archiveId, $cid, $context);
        if ($session->duration <= 0) {
            throw new \RuntimeException('视频时长无效');
        }

        $videoResponse = ($this->videoAction)($session->archiveId, $session->cid, $session->bvid, [
            'session' => $session->sessionId,
        ]);
        if (($videoResponse['code'] ?? -1) !== 0) {
            $code = (int)($videoResponse['code'] ?? -1);
            $message = trim((string)($videoResponse['message'] ?? $videoResponse['msg'] ?? ''));
            throw new \RuntimeException("视频观看初始化失败 {$code} -> {$message}");
        }

        $progress = max(1, $session->duration);
        $heartbeatResponse = ($this->heartbeatAction)($session->archiveId, $session->cid, $progress, $session->bvid, [
            'session' => $session->sessionId,
        ]);
        if (($heartbeatResponse['code'] ?? -1) !== 0) {
            $code = (int)($heartbeatResponse['code'] ?? -1);
            $message = trim((string)($heartbeatResponse['message'] ?? $heartbeatResponse['msg'] ?? ''));
            throw new \RuntimeException("视频观看首拍心跳失败 {$code} -> {$message}");
        }

        return $session;
    }

    public function finish(VideoWatchSession $session, int $watchedSeconds): bool
    {
        if (
            $watchedSeconds <= 0
            || trim($session->archiveId) === ''
            || trim($session->cid) === ''
            || $session->duration <= 0
        ) {
            return false;
        }

        $duration = max(1, $session->duration);
        $playedTime = $watchedSeconds >= $duration ? max(0, $duration - 1) : $watchedSeconds;
        $response = ($this->heartbeatAction)($session->archiveId, $session->cid, $duration, $session->bvid, [
            'played_time' => $playedTime,
            'play_type' => 0,
            'start_ts' => time(),
            'session' => $session->sessionId,
        ]);
        return ($response['code'] ?? -1) === 0;
    }
}
