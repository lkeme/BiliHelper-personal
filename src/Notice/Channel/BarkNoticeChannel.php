<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;

use Bhp\Log\Log;
use Bhp\Request\Request;

final class BarkNoticeChannel extends AbstractNoticeChannel
{
    public function name(): string
    {
        return 'bark';
    }

    public function supports(): bool
    {
        return trim((string)$this->config('notify_bark.token', '', 'string')) !== '';
    }

    public function dispatch(array $payload): void
    {
        Log::info('使用bark推送消息');
        $title = urlencode((string)$payload['title']);
        $content = urlencode((string)$payload['content']);
        $url = 'https://api.day.app/' . $this->config('notify_bark.token') . "/{$title}/{$content}";

        $decoded = \Bhp\Api\Support\ApiJson::get( 'other', $url, [], []);
        if (($decoded['code'] ?? -1) === 200) {
            Log::notice('推送消息成功: ' . (string)($decoded['message'] ?? ''));
            return;
        }

        Log::warning('推送消息失败: ' . json_encode($decoded));
    }
}
