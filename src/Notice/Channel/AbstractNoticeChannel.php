<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;

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

    protected function info(string $message, array $context = []): void
    {
        $this->context->log()->recordInfo($message, $context);
    }

    protected function notice(string $message, array $context = []): void
    {
        $this->context->log()->recordNotice($message, $context);
    }

    protected function warning(string $message, array $context = []): void
    {
        $this->context->log()->recordWarning($message, $context);
    }

    protected function requestGet(string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return $this->context->request()->getText('other', $url, $params, $headers, $timeout);
    }

    protected function requestPost(string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return $this->context->request()->postText('other', $url, $params, $headers, $timeout);
    }

    protected function requestPostJsonBody(string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return $this->context->request()->postJsonBodyText('other', $url, $params, $headers, $timeout);
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
        $this->warning("推送消息失败: {$raw}");
    }
}
