<?php declare(strict_types=1);

namespace Bhp\Config;

final class ConfigSchemaDefinition
{
    /**
     * @return array<string, ConfigFieldRule>
     */
    public function fieldRules(): array
    {
        return [
            'login_mode.mode' => new ConfigFieldRule(
                key: 'login_mode.mode',
                allowedValues: [1, 2],
            ),
            'main_site.fetch_aids_mode' => new ConfigFieldRule(
                key: 'main_site.fetch_aids_mode',
                allowedValues: ['random', 'fixed'],
            ),
            'app.branch' => new ConfigFieldRule(
                key: 'app.branch',
                allowedValues: ['master', 'dev'],
            ),
            'request_governance.mode' => new ConfigFieldRule(
                key: 'request_governance.mode',
                allowedValues: ['observe', 'enforce'],
            ),
            'login_captcha.url' => new ConfigFieldRule(
                key: 'login_captcha.url',
                validateUrl: true,
                requiredWhenEnabledBy: 'login_captcha.enable',
                emptyMessage: 'login_captcha.enable 开启时，login_captcha.url 不能为空',
                invalidMessage: 'login_captcha.url 不是有效的服务地址',
            ),
            'network_proxy.proxy' => new ConfigFieldRule(
                key: 'network_proxy.proxy',
                validateUrl: true,
                requiredWhenEnabledBy: 'network_proxy.enable',
                emptyMessage: 'network_proxy.enable 开启时，network_proxy.proxy 不能为空',
                invalidMessage: 'network_proxy.proxy 不是有效的代理 URL',
            ),
            'network_github.mirror' => new ConfigFieldRule(
                key: 'network_github.mirror',
                validateUrl: true,
                invalidMessage: 'network_github.mirror 不是有效的 URL',
            ),
            'notify_telegram.url' => new ConfigFieldRule(
                key: 'notify_telegram.url',
                validateUrl: true,
                invalidMessage: 'notify_telegram.url 不是有效的 URL',
            ),
            'notify_gocqhttp.url' => new ConfigFieldRule(
                key: 'notify_gocqhttp.url',
                validateUrl: true,
                invalidMessage: 'notify_gocqhttp.url 不是有效的 URL',
            ),
            'notify_debug.url' => new ConfigFieldRule(
                key: 'notify_debug.url',
                validateUrl: true,
                invalidMessage: 'notify_debug.url 不是有效的 URL',
            ),
            'notify_push_deer.url' => new ConfigFieldRule(
                key: 'notify_push_deer.url',
                validateUrl: true,
                invalidMessage: 'notify_push_deer.url 不是有效的 URL',
            ),
            'log.callback' => new ConfigFieldRule(
                key: 'log.callback',
                validateUrl: true,
                invalidMessage: 'log.callback 不是有效的 URL',
            ),
        ];
    }

    public function exampleConfigPath(): string
    {
        return str_replace("\\", "/", dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'example' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'user.ini');
    }
}
