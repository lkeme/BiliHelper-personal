<?php declare(strict_types=1);

namespace Bhp\Notice\Channel;

use Bhp\Notice\NoticeChannel;
use Bhp\Runtime\AppContext;

abstract class AbstractNoticeChannel implements NoticeChannel
{
    /**
     * 初始化 AbstractNoticeChannel
     * @param AppContext $context
     */
    public function __construct(protected readonly AppContext $context)
    {
    }

    /**
     * 处理配置
     * @param string $key
     * @param mixed $default
     * @param string $type
     * @return mixed
     */
    protected function config(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return $this->context->config($key, $default, $type);
    }

    /**
     * 处理信息
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function info(string $message, array $context = []): void
    {
        $this->context->log()->recordInfo($message, $context);
    }

    /**
     * 处理通知
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function notice(string $message, array $context = []): void
    {
        $this->context->log()->recordNotice($message, $context);
    }

    /**
     * 处理warning
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function warning(string $message, array $context = []): void
    {
        $this->context->log()->recordWarning($message, $context);
    }

    /**
     * 请求Get
     * @param string $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return string
     */
    protected function requestGet(string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return $this->context->request()->getText('other', $url, $params, $headers, $timeout);
    }

    /**
     * 请求Post
     * @param string $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return string
     */
    protected function requestPost(string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return $this->context->request()->postText('other', $url, $params, $headers, $timeout);
    }

    /**
     * 请求PostJSONBody
     * @param string $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return string
     */
    protected function requestPostJsonBody(string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return $this->context->request()->postJsonBodyText('other', $url, $params, $headers, $timeout);
    }

    /**
     * @return array<string, string>
     */
    protected function jsonHeaders(string $contentType = 'application/json'): array
    {
        return [
            'Content-Type' => $contentType,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(string $raw): array
    {
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 处理日志失败
     * @param string $raw
     * @return void
     */
    protected function logFailure(string $raw): void
    {
        $this->warning("推送消息失败: {$raw}");
    }
}
