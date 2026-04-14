<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;


final class GoCqhttpNoticeChannel extends AbstractNoticeChannel
{
    /**
     * 处理名称
     * @return string
     */
    public function name(): string
    {
        return 'gocqhttp';
    }

    /**
     * 处理supports
     * @return bool
     */
    public function supports(): bool
    {
        return trim((string)$this->config('notify_gocqhttp.target_qq', '', 'string')) !== ''
            && trim((string)$this->config('notify_gocqhttp.token', '', 'string')) !== ''
            && trim((string)$this->config('notify_gocqhttp.url', '', 'string')) !== '';
    }

    /**
     * 处理分发
     * @param array $payload
     * @return void
     */
    public function dispatch(array $payload): void
    {
        $this->info('使用GoCqhttp推送消息');
        $raw = $this->requestGet((string)$this->config('notify_gocqhttp.url'), [
            'access_token' => $this->config('notify_gocqhttp.token'),
            'user_id' => $this->config('notify_gocqhttp.target_qq'),
            'message' => $payload['content'],
        ]);

        $decoded = $this->decode($raw);
        if (($decoded['retcode'] ?? -1) === 0) {
            $this->notice('推送消息成功: ' . (string)($decoded['status'] ?? ''));
            return;
        }

        $this->logFailure($raw);
    }
}
