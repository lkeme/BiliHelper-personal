<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;

use Bhp\Log\Log;
use Bhp\Notice\NoticeChannel;
use Bhp\Runtime\AppContext;

abstract class AbstractNoticeChannel implements NoticeChannel
{
    public function __construct(protected readonly AppContext $context)
    {
    }

    protected function config(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return $this->context->config($key, $default, $type);
    }

    /**
     * @return array<string, string>
     */
    protected function jsonHeaders(string $contentType = 'application/json'): array
    {
        return [
            'Content-Type' => $contentType,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(string $raw): array
    {
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function logFailure(string $raw): void
    {
        Log::warning("推送消息失败: {$raw}");
    }
}
