<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;


final class ScNoticeChannel extends AbstractNoticeChannel
{
    public function name(): string
    {
        return 'server-chan';
    }

    public function supports(): bool
    {
        return trim((string)$this->config('notify_sc.sckey', '', 'string')) !== '';
    }

    public function dispatch(array $payload): void
    {
        $this->info('使用ServerChan推送消息');
        $url = 'https://sc.ftqq.com/' . $this->config('notify_sc.sckey') . '.send';
        $raw = $this->requestPost($url, [
            'text' => $payload['title'],
            'desp' => $payload['content'],
        ]);

        $decoded = $this->decode($raw);
        if (($decoded['data']['errno'] ?? -1) === 0) {
            $this->notice('推送消息成功: ' . (string)($decoded['data']['errmsg'] ?? ''));
            return;
        }

        $this->logFailure($raw);
    }
}
