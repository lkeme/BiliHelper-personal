<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;

use Bhp\Log\Log;
use Bhp\Request\Request;

final class PushPlusNoticeChannel extends AbstractNoticeChannel
{
    public function name(): string
    {
        return 'pushplus';
    }

    public function supports(): bool
    {
        return trim((string)$this->config('notify_pushplus.token', '', 'string')) !== '';
    }

    public function dispatch(array $payload): void
    {
        Log::info('使用PushPlus酱推送消息');
        $raw = Request::postJsonBody('other', 'https://www.pushplus.plus/send', [
            'token' => $this->config('notify_pushplus.token'),
            'title' => $payload['title'],
            'content' => $payload['content'],
        ], $this->jsonHeaders());

        $decoded = $this->decode($raw);
        if (($decoded['code'] ?? -1) === 200) {
            Log::notice('推送消息成功: ' . (string)($decoded['data'] ?? ''));
            return;
        }

        $this->logFailure($raw);
    }
}
