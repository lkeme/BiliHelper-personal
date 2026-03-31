<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;

use Bhp\Log\Log;
use Bhp\Request\Request;

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
        Log::info('使用Debug推送消息');
        $raw = Request::postJsonBody('other', (string)$this->config('notify_debug.url'), [
            'token' => $this->config('notify_debug.token'),
            'title' => $payload['title'],
            'content' => $payload['content'],
            'link' => '',
            'tags' => 'Debug',
        ], $this->jsonHeaders());

        $decoded = $this->decode($raw);
        if (($decoded['data']['status'] ?? -1) === 0) {
            Log::notice('推送消息成功: ' . (string)($decoded['data']['message'] ?? ''));
            return;
        }

        $this->logFailure($raw);
    }
}
