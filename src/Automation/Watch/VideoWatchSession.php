<?php declare(strict_types=1);

namespace Bhp\Automation\Watch;

final class VideoWatchSession
{
    public function __construct(
        public readonly string $archiveId,
        public readonly string $cid,
        public readonly string $sessionId,
        public readonly string $bvid = '',
        public readonly int $duration = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function start(string $archiveId, string $cid, array $context = []): self
    {
        $sessionId = trim((string)($context['session_id'] ?? ''));
        if ($sessionId === '') {
            $sessionId = self::generateSessionId();
        }

        return new self(
            trim($archiveId),
            trim($cid),
            $sessionId,
            trim((string)($context['bvid'] ?? '')),
            max(0, (int)($context['duration'] ?? 0)),
        );
    }

    private static function generateSessionId(): string
    {
        try {
            return strtolower(bin2hex(random_bytes(16)));
        } catch (\Throwable) {
            return strtolower(md5(uniqid((string)mt_rand(), true)));
        }
    }
}
