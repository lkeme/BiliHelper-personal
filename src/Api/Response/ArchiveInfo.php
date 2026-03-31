<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class ArchiveInfo
{
    public function __construct(
        public readonly string $aid,
        public readonly string $cid,
        public readonly int $duration,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['aid'] ?? ''),
            (string)($data['cid'] ?? ''),
            (int)($data['duration'] ?? 0),
        );
    }
}
