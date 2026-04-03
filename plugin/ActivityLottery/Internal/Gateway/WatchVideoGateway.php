<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Gateway;

use Bhp\Plugin\ActivityLottery\Internal\EraTopicArchiveService;
use Bhp\Plugin\ActivityLottery\Internal\EraVideoWatchService;

final class WatchVideoGateway
{
    /**
     * @var callable(array<string, mixed>): ?array<string, mixed>
     */
    private readonly mixed $archiveNormalizer;
    /**
     * @var callable(string, int): array<int, array<string, mixed>>
     */
    private readonly mixed $topicArchiveFetcher;
    /**
     * @var callable(array<string, mixed>, string): bool
     */
    private readonly mixed $startAction;
    /**
     * @var callable(array<string, mixed>, int, string): bool
     */
    private readonly mixed $finishAction;

    public function __construct(
        ?callable $archiveNormalizer = null,
        ?callable $topicArchiveFetcher = null,
        ?callable $startAction = null,
        ?callable $finishAction = null,
    ) {
        $watchService = new EraVideoWatchService();
        $topicService = new EraTopicArchiveService();

        $this->archiveNormalizer = $archiveNormalizer ?? fn (array $archive): ?array => $watchService->normalizeArchive($archive);
        $this->topicArchiveFetcher = $topicArchiveFetcher ?? fn (string $topicId, int $limit = 20): array => $topicService->fetchArchives($topicId, $limit);
        $this->startAction = $startAction ?? fn (array $archive, string $sessionId): bool => $watchService->start($archive, $sessionId);
        $this->finishAction = $finishAction ?? fn (array $archive, int $watchedSeconds, string $sessionId): bool => $watchService->finish($archive, null, $watchedSeconds, $sessionId);
    }

    /**
     * @param array<string, mixed> $archive
     * @return array<string, mixed>|null
     */
    public function normalizeArchive(array $archive): ?array
    {
        $normalized = ($this->archiveNormalizer)($archive);
        if (!is_array($normalized) || $normalized === []) {
            return null;
        }

        $aid = trim((string)($normalized['aid'] ?? ''));
        $bvid = trim((string)($normalized['bvid'] ?? ''));
        if ($aid === '' && $bvid === '') {
            return null;
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchTopicArchives(string $topicId, int $limit = 20): array
    {
        $archives = ($this->topicArchiveFetcher)($topicId, $limit);
        return is_array($archives) ? array_values(array_filter($archives, 'is_array')) : [];
    }

    /**
     * @param array<string, mixed> $archive
     */
    public function start(array $archive, string $sessionId): bool
    {
        return (bool)($this->startAction)($archive, $sessionId);
    }

    /**
     * @param array<string, mixed> $archive
     */
    public function finish(array $archive, int $watchedSeconds, string $sessionId): bool
    {
        return (bool)($this->finishAction)($archive, $watchedSeconds, $sessionId);
    }
}
