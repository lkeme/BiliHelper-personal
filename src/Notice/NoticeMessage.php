<?php declare(strict_types=1);

namespace Bhp\Notice;

final class NoticeMessage
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $content,
        public readonly array $meta = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'type' => $this->type,
            'meta' => $this->meta,
        ];
    }
}
