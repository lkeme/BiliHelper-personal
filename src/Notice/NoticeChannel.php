<?php declare(strict_types=1);

namespace Bhp\Notice;

interface NoticeChannel
{
    public function name(): string;

    public function supports(): bool;

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(array $payload): void;
}
