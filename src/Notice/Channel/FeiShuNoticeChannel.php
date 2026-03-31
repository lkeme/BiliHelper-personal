<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;

use Bhp\Log\Log;
use Bhp\Request\Request;

final class FeiShuNoticeChannel extends AbstractNoticeChannel
{
    public function name(): string
    {
        return 'feishu';
    }

    public function supports(): bool
    {
        return trim((string)$this->config('notify_feishu.token', '', 'string')) !== '';
    }

    public function dispatch(array $payload): void
    {
        Log::info('使用飞书webhook机器人推送消息');
        $url = 'https://open.feishu.cn/open-apis/bot/v2/hook/' . $this->config('notify_feishu.token');
        $raw = Request::postJsonBody('other', $url, [
            'msg_type' => 'text',
            'content' => [
                'text' => (string)$payload['title'] . (string)$payload['content'],
            ],
        ], $this->jsonHeaders('application/json;charset=utf-8'));

        $decoded = $this->decode($raw);
        if (($decoded['StatusCode'] ?? null) === 0) {
            Log::notice('推送消息成功: ' . (string)$decoded['StatusCode']);
            return;
        }

        $this->logFailure($raw);
    }
}
