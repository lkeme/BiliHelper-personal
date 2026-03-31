<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;

use Bhp\Log\Log;
use Bhp\Request\Request;

final class DingTalkNoticeChannel extends AbstractNoticeChannel
{
    public function name(): string
    {
        return 'dingtalk';
    }

    public function supports(): bool
    {
        return trim((string)$this->config('notify_dingtalk.token', '', 'string')) !== '';
    }

    public function dispatch(array $payload): void
    {
        Log::info('使用DingTalk机器人推送消息');
        $url = 'https://oapi.dingtalk.com/robot/send?access_token=' . $this->config('notify_dingtalk.token');
        $raw = Request::postJsonBody('other', $url, [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $payload['title'],
                'text' => $payload['content'],
            ],
        ], $this->jsonHeaders('application/json;charset=utf-8'));

        $decoded = $this->decode($raw);
        if (($decoded['errcode'] ?? -1) === 0) {
            Log::notice('推送消息成功: ' . (string)($decoded['errmsg'] ?? ''));
            return;
        }

        $this->logFailure($raw);
    }
}
