<?php declare(strict_types=1);

namespace Bhp\ArticleSource;

final class SpaceArticleCandidate
{
    public function __construct(
        public readonly int $cvId,
        public readonly string $title,
        public readonly string $summary,
        public readonly int $publishTime,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArticleRow(array $row): ?self
    {
        $cvId = (int)($row['id'] ?? 0);
        $title = trim((string)($row['title'] ?? ''));
        $summary = trim((string)($row['summary'] ?? ''));
        $publishTime = (int)($row['publish_time'] ?? 0);
        if ($cvId <= 0 || $title === '' || $publishTime <= 0) {
            return null;
        }

        return new self($cvId, $title, $summary, $publishTime);
    }
}
