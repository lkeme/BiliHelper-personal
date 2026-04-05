<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;


final class WeComNoticeChannel extends AbstractNoticeChannel
{
    public function name(): string
    {
        return 'wecom';
    }

    public function supports(): bool
    {
        return trim((string)$this->config('notify_we_com.token', '', 'string')) !== '';
    }

    public function dispatch(array $payload): void
    {
        $this->info('使用weCom推送消息');
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=' . $this->config('notify_we_com.token');
        $raw = $this->requestPostJsonBody($url, [
            'msgtype' => 'markdown',
            'markdown' => [
                'content' => $payload['title'] . " \n\n" . $payload['content'],
            ],
        ], $this->jsonHeaders());

        $decoded = $this->decode($raw);
        if (($decoded['errcode'] ?? -1) === 0) {
            $this->notice('推送消息成功: ' . (string)($decoded['errmsg'] ?? ''));
            return;
        }

        $this->logFailure($raw);
    }
}
