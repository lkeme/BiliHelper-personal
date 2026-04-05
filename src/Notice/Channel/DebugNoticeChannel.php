<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;


final class DebugNoticeChannel extends AbstractNoticeChannel
{
    public function name(): string
    {
        return 'debug';
    }

    public function supports(): bool
    {
        return trim((string)$this->config('notify_debug.token', '', 'string')) !== ''
            && trim((string)$this->config('notify_debug.url', '', 'string')) !== '';
    }

    public function dispatch(array $payload): void
    {
        $this->info('使用Debug推送消息');
        $raw = $this->requestPostJsonBody((string)$this->config('notify_debug.url'), [
            'token' => $this->config('notify_debug.token'),
            'title' => $payload['title'],
            'content' => $payload['content'],
            'link' => '',
            'tags' => 'Debug',
        ], $this->jsonHeaders());

        $decoded = $this->decode($raw);
        if (($decoded['data']['status'] ?? -1) === 0) {
            $this->notice('推送消息成功: ' . (string)($decoded['data']['message'] ?? ''));
            return;
        }

        $this->logFailure($raw);
    }
}
