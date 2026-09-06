<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;


final class WPushNoticeChannel extends AbstractNoticeChannel
{
    /**
     * 处理名称
     * @return string
     */
    public function name(): string
    {
        return 'wpush';
    }

    /**
     * 处理supports
     * @return bool
     */
    public function supports(): bool
    {
        return trim((string)$this->config('notify_wpush.apikey', '', 'string')) !== '';
    }

    /**
     * 处理分发
     * @param array $payload
     * @return void
     */
    public function dispatch(array $payload): void
    {
        $this->info('使用WPUSH推送消息');
        $body = [
            'apikey' => $this->config('notify_wpush.apikey'),
            'title' => $payload['title'],
            'content' => $payload['content'],
        ];

        $channel = trim((string)$this->config('notify_wpush.channel', '', 'string'));
        if ($channel !== '') {
            $body['channel'] = $channel;
        }

        $topicCode = trim((string)$this->config('notify_wpush.topic_code', '', 'string'));
        if ($topicCode !== '') {
            $body['topic_code'] = $topicCode;
        }

        $raw = $this->requestPostJsonBody('https://api.wpush.cn/api/v1/send', $body, $this->jsonHeaders());

        $decoded = $this->decode($raw);
        if (($decoded['code'] ?? -1) === 0) {
            $this->notice('推送请求已接受: ' . (string)($decoded['message'] ?? $decoded['data'] ?? ''));
            return;
        }

        $this->logFailure($raw);
    }
}
