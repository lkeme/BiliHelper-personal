<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;


final class WeComNoticeChannel extends AbstractNoticeChannel
{
    /**
     * 处理名称
     * @return string
     */
    public function name(): string
    {
        return 'wecom';
    }

    /**
     * 处理supports
     * @return bool
     */
    public function supports(): bool
    {
        return trim((string)$this->config('notify_we_com.token', '', 'string')) !== '';
    }

    /**
     * 处理分发
     * @param array $payload
     * @return void
     */
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
