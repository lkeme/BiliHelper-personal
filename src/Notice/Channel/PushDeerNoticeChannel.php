<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;

use Bhp\Log\Log;
use Bhp\Request\Request;

final class PushDeerNoticeChannel extends AbstractNoticeChannel
{
    public function name(): string
    {
        return 'pushdeer';
    }

    public function supports(): bool
    {
        return trim((string)$this->config('notify_push_deer.token', '', 'string')) !== '';
    }

    public function dispatch(array $payload): void
    {
        Log::info('使用 PushDeer 推送消息');
        $url = (string)$this->config('notify_push_deer.url', '', 'string');
        if (!str_contains($url, 'http')) {
            $url = 'https://api2.pushdeer.com/message/push';
        }

        $raw = Request::post('other', $url, [
            'text' => $payload['title'],
            'desp' => $payload['content'],
            'pushkey' => $this->config('notify_push_deer.token'),
        ], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);

        $decoded = $this->decode($raw);
        if (($decoded['code'] ?? -1) === 0) {
            Log::notice('推送消息成功: ' . (string)($decoded['content']['result'][0] ?? ''));
            return;
        }

        $this->logFailure($raw);
    }
}
