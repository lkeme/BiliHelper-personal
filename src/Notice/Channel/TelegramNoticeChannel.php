<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;

use Bhp\Log\Log;
use Bhp\Request\Request;

final class TelegramNoticeChannel extends AbstractNoticeChannel
{
    public function name(): string
    {
        return 'telegram';
    }

    public function supports(): bool
    {
        return trim((string)$this->config('notify_telegram.bottoken', '', 'string')) !== ''
            && trim((string)$this->config('notify_telegram.chatid', '', 'string')) !== '';
    }

    public function dispatch(array $payload): void
    {
        Log::info('使用Tele机器人推送消息');
        $baseUrl = (string)$this->config('notify_telegram.url', '', 'string');
        if ($baseUrl === '') {
            $baseUrl = 'https://api.telegram.org/bot';
        }

        $url = $baseUrl . $this->config('notify_telegram.bottoken') . '/sendMessage';
        $raw = Request::post('other', $url, [
            'chat_id' => $this->config('notify_telegram.chatid'),
            'text' => $payload['content'],
        ]);

        $decoded = $this->decode($raw);
        if (($decoded['ok'] ?? false) === true && array_key_exists('message_id', $decoded['result'] ?? [])) {
            Log::notice('推送消息成功: MSG_ID->' . (string)$decoded['result']['message_id']);
            return;
        }

        $this->logFailure($raw);
    }
}
