<?php declare(strict_types=1);

namespace Bhp\Notice;

interface NoticeChannel
{
    /**
     * 处理名称
     * @return string
     */
    public function name(): string;

    /**
     * 处理supports
     * @return bool
     */
    public function supports(): bool;

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(array $payload): void;
}
