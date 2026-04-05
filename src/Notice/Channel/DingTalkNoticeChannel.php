<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;


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
        $this->info('使用DingTalk机器人推送消息');
        $url = 'https://oapi.dingtalk.com/robot/send?access_token=' . $this->config('notify_dingtalk.token');
        $raw = $this->requestPostJsonBody($url, [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $payload['title'],
                'text' => $payload['content'],
            ],
        ], $this->jsonHeaders('application/json;charset=utf-8'));

        $decoded = $this->decode($raw);
        if (($decoded['errcode'] ?? -1) === 0) {
            $this->notice('推送消息成功: ' . (string)($decoded['errmsg'] ?? ''));
            return;
        }

        $this->logFailure($raw);
    }
}
