<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;


final class SctNoticeChannel extends AbstractNoticeChannel
{
    public function name(): string
    {
        return 'server-chan-turbo';
    }

    public function supports(): bool
    {
        return trim((string)$this->config('notify_sct.sctkey', '', 'string')) !== '';
    }

    public function dispatch(array $payload): void
    {
        $this->info('使用ServerChan(Turbo)推送消息');
        $url = 'https://sctapi.ftqq.com/' . $this->config('notify_sct.sctkey') . '.send';
        $raw = $this->requestPost($url, [
            'text' => $payload['title'],
            'desp' => $payload['content'],
        ]);

        $decoded = $this->decode($raw);
        if (($decoded['code'] ?? -1) === 0) {
            $this->notice('推送消息成功: ' . (string)($decoded['data']['pushid'] ?? ''));
            return;
        }

        $this->logFailure($raw);
    }
}
