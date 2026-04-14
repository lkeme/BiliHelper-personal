<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;


final class FeiShuNoticeChannel extends AbstractNoticeChannel
{
    /**
     * 处理名称
     * @return string
     */
    public function name(): string
    {
        return 'feishu';
    }

    /**
     * 处理supports
     * @return bool
     */
    public function supports(): bool
    {
        return trim((string)$this->config('notify_feishu.token', '', 'string')) !== '';
    }

    /**
     * 处理分发
     * @param array $payload
     * @return void
     */
    public function dispatch(array $payload): void
    {
        $this->info('使用飞书webhook机器人推送消息');
        $url = 'https://open.feishu.cn/open-apis/bot/v2/hook/' . $this->config('notify_feishu.token');
        $text = trim((string)$payload['title'] . PHP_EOL . (string)$payload['content']);
        $raw = $this->requestPostJsonBody($url, [
            'msg_type' => 'text',
            'content' => [
                'text' => $text,
            ],
        ], $this->jsonHeaders('application/json;charset=utf-8'));

        $decoded = $this->decode($raw);
        if (($decoded['StatusCode'] ?? null) === 0) {
            $this->notice('推送消息成功: ' . (string)$decoded['StatusCode']);
            return;
        }

        $this->logFailure($raw);
    }
}
