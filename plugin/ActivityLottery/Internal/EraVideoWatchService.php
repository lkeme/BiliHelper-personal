<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal;

use Bhp\Api\Api\X\Player\ApiPlayer;
use Bhp\Api\Video\ApiWatch;

final class EraVideoWatchService
{
    /**
     * @param array<string, mixed> $archive
     * @return array<string, mixed>|null
     */
    public function normalizeArchiveIdentity(array $archive): ?array
    {
        $aid = trim((string)($archive['aid'] ?? ''));
        $bvid = trim((string)($archive['bvid'] ?? ''));
        if ($aid === '' && $bvid === '') {
            return null;
        }

        if ($aid === '' && $bvid !== '') {
            $aid = $this->aidFromBvid($bvid);
        }

        if ($aid === '') {
            return null;
        }

        return [
            'aid' => $aid,
            'bvid' => $bvid,
            'title' => trim((string)($archive['title'] ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveArchive(EraActivityTask $task): ?array
    {
        foreach ($task->targetArchives as $archive) {
            if (!is_array($archive)) {
                continue;
            }

            $normalized = $this->normalizeArchive($archive);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        foreach ($task->targetVideoIds as $videoId) {
            $normalized = $this->normalizeArchive([
                'aid' => ctype_digit((string)$videoId) ? (string)$videoId : '',
                'bvid' => str_starts_with(strtoupper((string)$videoId), 'BV') ? (string)$videoId : '',
            ]);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $archive
     * @return array<string, mixed>|null
     */
    public function normalizeArchive(array $archive): ?array
    {
        $aid = trim((string)($archive['aid'] ?? ''));
        $bvid = trim((string)($archive['bvid'] ?? ''));
        if ($aid === '' && $bvid === '') {
            return null;
        }

        if ($aid === '' && $bvid !== '') {
            $aid = $this->aidFromBvid($bvid);
        }

        $cid = trim((string)($archive['cid'] ?? ''));
        $duration = (int)($archive['duration'] ?? 0);
        if ($aid !== '' && $cid !== '' && $duration > 0) {
            return [
                'aid' => $aid,
                'cid' => $cid,
                'duration' => $duration,
                'title' => trim((string)($archive['title'] ?? '')),
                'bvid' => $bvid,
            ];
        }

        $response = ApiPlayer::pageList($aid, $bvid);
        if (($response['code'] ?? 0) !== 0 || !isset($response['data'][0]) || !is_array($response['data'][0])) {
            return null;
        }

        $page = $response['data'][0];
        $cid = trim((string)($page['cid'] ?? ''));
        $duration = (int)($page['duration'] ?? 0);
        if ($cid === '' || $duration <= 0) {
            return null;
        }

        return [
            'aid' => $aid,
            'cid' => $cid,
            'duration' => $duration,
            'title' => trim((string)($archive['title'] ?? $page['part'] ?? '')),
            'bvid' => $bvid,
        ];
    }

    public function start(array $archive, ?string $session = null): bool
    {
        $archive = $this->normalizeArchive($archive);
        if ($archive === null) {
            return false;
        }

        $aid = (string)($archive['aid'] ?? '');
        $cid = (string)($archive['cid'] ?? '');
        $duration = (int)($archive['duration'] ?? 0);
        $bvid = (string)($archive['bvid'] ?? '');
        $session ??= $this->generateSession();

        $response = ApiWatch::video($aid, $cid, $bvid, [
            'session' => $session,
        ]);
        if (($response['code'] ?? -1) !== 0) {
            return false;
        }

        $response = ApiWatch::heartbeat($aid, $cid, $duration, $bvid, [
            'session' => $session,
        ]);
        return ($response['code'] ?? -1) === 0;
    }

    public function finish(array $archive, ?int $startedAt = null, ?int $watchedSeconds = null, ?string $session = null): bool
    {
        $archive = $this->normalizeArchive($archive);
        if ($archive === null) {
            return false;
        }

        $aid = (string)($archive['aid'] ?? '');
        $cid = (string)($archive['cid'] ?? '');
        $duration = (int)($archive['duration'] ?? 0);
        $bvid = (string)($archive['bvid'] ?? '');
        $watchedSeconds = max(1, min($duration, (int)($watchedSeconds ?? $duration)));
        $session ??= $this->generateSession();

        $response = ApiWatch::heartbeat($aid, $cid, $duration, $bvid, [
            'played_time' => $watchedSeconds >= $duration ? max(0, $duration - 1) : $watchedSeconds,
            'play_type' => 0,
            'start_ts' => time(),
            'session' => $session,
        ]);

        return ($response['code'] ?? -1) === 0;
    }

    private function generateSession(): string
    {
        try {
            return strtolower(bin2hex(random_bytes(16)));
        } catch (\Throwable) {
            return strtolower(md5(uniqid((string)mt_rand(), true)));
        }
    }

    private function aidFromBvid(string $bvid): string
    {
        $bvid = trim($bvid);
        if ($bvid === '' || strlen($bvid) < 12) {
            return '';
        }

        $alphabet = 'fZodR9XQDSUm21yCkLt3xa4bgh5e6ivqB8wpHnJE7jKNPVYcfruM9sTzG';
        $map = [];
        $length = strlen($alphabet);
        for ($index = 0; $index < $length; $index++) {
            $map[$alphabet[$index]] = $index;
        }

        $positions = [11, 10, 3, 8, 4, 6];
        $result = 0;
        foreach ($positions as $power => $position) {
            $char = $bvid[$position] ?? '';
            if ($char === '' || !isset($map[$char])) {
                return '';
            }

            $result += $map[$char] * (58 ** $power);
        }

        return (string)(($result - 8728348608) ^ 177451812);
    }
}
