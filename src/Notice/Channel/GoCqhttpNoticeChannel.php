<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;

use Bhp\Log\Log;
use Bhp\Request\Request;

final class GoCqhttpNoticeChannel extends AbstractNoticeChannel
{
    public function name(): string
    {
        return 'gocqhttp';
    }

    public function supports(): bool
    {
        return trim((string)$this->config('notify_gocqhttp.target_qq', '', 'string')) !== ''
            && trim((string)$this->config('notify_gocqhttp.token', '', 'string')) !== ''
            && trim((string)$this->config('notify_gocqhttp.url', '', 'string')) !== '';
    }

    public function dispatch(array $payload): void
    {
        Log::info('使用GoCqhttp推送消息');
        $raw = Request::get('other', (string)$this->config('notify_gocqhttp.url'), [
            'access_token' => $this->config('notify_gocqhttp.token'),
            'user_id' => $this->config('notify_gocqhttp.target_qq'),
            'message' => $payload['content'],
        ]);

        $decoded = $this->decode($raw);
        if (($decoded['retcode'] ?? -1) === 0) {
            Log::notice('推送消息成功: ' . (string)($decoded['status'] ?? ''));
            return;
        }

        $this->logFailure($raw);
    }
}
