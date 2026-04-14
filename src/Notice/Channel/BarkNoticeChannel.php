<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;

use Bhp\Api\Support\ApiJson;

final class BarkNoticeChannel extends AbstractNoticeChannel
{
    /**
     * 处理名称
     * @return string
     */
    public function name(): string
    {
        return 'bark';
    }

    /**
     * 处理supports
     * @return bool
     */
    public function supports(): bool
    {
        return trim((string)$this->config('notify_bark.token', '', 'string')) !== '';
    }

    /**
     * 处理分发
     * @param array $payload
     * @return void
     */
    public function dispatch(array $payload): void
    {
        $this->info('使用bark推送消息');
        $title = urlencode((string)$payload['title']);
        $content = urlencode((string)$payload['content']);
        $url = 'https://api.day.app/' . $this->config('notify_bark.token') . "/{$title}/{$content}";

        $raw = $this->requestGet($url);
        $decoded = ApiJson::decode($raw, 'notice.bark.dispatch');
        if (($decoded['code'] ?? -1) === 200) {
            $this->notice('推送消息成功: ' . (string)($decoded['message'] ?? ''));
            return;
        }

        $this->warning('推送消息失败: ' . json_encode($decoded));
    }
}
