<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class SpaceArticleSummary
{
    /**
     * 初始化 SpaceArticleSummary
     * @param int $id
     * @param string $title
     * @param int $publishTime
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly int $publishTime,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int)($data['id'] ?? 0),
            (string)($data['title'] ?? ''),
            (int)($data['publish_time'] ?? 0),
        );
    }
}
