<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;


final class WeComAppNoticeChannel extends AbstractNoticeChannel
{
    /**
     * 处理名称
     * @return string
     */
    public function name(): string
    {
        return 'wecom-app';
    }

    /**
     * 处理supports
     * @return bool
     */
    public function supports(): bool
    {
        return trim((string)$this->config('notify_we_com_app.corp_id', '', 'string')) !== ''
            && trim((string)$this->config('notify_we_com_app.corp_secret', '', 'string')) !== ''
            && trim((string)$this->config('notify_we_com_app.agent_id', '', 'string')) !== '';
    }

    /**
     * 处理分发
     * @param array $payload
     * @return void
     */
    public function dispatch(array $payload): void
    {
        $this->info('使用weComApp推送消息');
        $tokenResponse = $this->requestGet('https://qyapi.weixin.qq.com/cgi-bin/gettoken', [
            'corpid' => $this->config('notify_we_com_app.corp_id'),
            'corpsecret' => $this->config('notify_we_com_app.corp_secret'),
        ]);

        $tokenDecoded = $this->decode($tokenResponse);
        if (($tokenDecoded['errcode'] ?? -1) !== 0) {
            $this->warning('access_token 获取失败', [
                'errcode' => $tokenDecoded['errcode'] ?? null,
                'errmsg' => $tokenDecoded['errmsg'] ?? null,
            ]);
            return;
        }

        $this->info('access_token 获取成功');

        $sendRaw = $this->requestPostJsonBody('https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=' . (string)$tokenDecoded['access_token'],
            [
                'touser' => $this->config('notify_we_com_app.to_user') ?: '@all',
                'msgtype' => 'text',
                'agentid' => $this->config('notify_we_com_app.agent_id'),
                'text' => [
                    'content' => $payload['title'] . " \n\n" . $payload['content'],
                ],
            ],
            $this->jsonHeaders()
        );

        $sendDecoded = $this->decode($sendRaw);
        if (($sendDecoded['errcode'] ?? -1) === 0) {
            $this->notice('推送消息成功: ' . (string)($sendDecoded['errmsg'] ?? ''));
            return;
        }

        $this->logFailure($sendRaw);
    }
}
