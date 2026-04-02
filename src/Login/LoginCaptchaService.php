<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Passport\ApiCaptcha;
use Bhp\Runtime\AppContext;
use Bhp\Util\Common\Common;
use Bhp\Util\Exceptions\LoginException;

class LoginCaptchaService
{
    public function __construct(protected AppContext $context)
    {
    }

    /**
     * @return array{gt:string,challenge:string}
     */
    public function matchCaptcha(string $targetUrl): array
    {
        preg_match('/gt=([a-f0-9]+)/', $targetUrl, $matches);
        $gt = $matches[1] ?? '';
        preg_match('/challenge=([a-f0-9]+)/', $targetUrl, $matches);
        $challenge = $matches[1] ?? '';
        if ($gt === '' || $challenge === '') {
            throw new LoginException('提取验证码失败', 600);
        }

        return ['gt' => $gt, 'challenge' => $challenge];
    }

    public function assertCaptchaServiceReady(): void
    {
        $errorMessage = '请参考以下验证码文档(https://github.com/lkeme/BiliHelper-personal/blob/master/docs/CAPTCHA.md)修正';
        if (!(($this->context->config('login_captcha.url')) && $this->context->enabled('login_captcha'))) {
            throw new LoginException("验证码识别并未开启，$errorMessage", 6 * 3600);
        }

        $serverInfo = parse_url((string)$this->context->config('login_captcha.url'));
        if (!isset($serverInfo['host']) || !isset($serverInfo['port'])) {
            throw new LoginException("验证码识别服务器配置错误，$errorMessage", 6 * 3600);
        }

        if (!Common::scanPort($serverInfo['host'], (int)$serverInfo['port'])) {
            throw new LoginException("验证码识别服务器端口不通，$errorMessage", 600);
        }
    }

    /**
     * @return array{state:string,data:array<string, string>,message:string}
     */
    public function pollCaptchaResult(string $challenge, int $expiresAt): array
    {
        if ($expiresAt < time()) {
            throw new LoginException('验证码识别超时', 300);
        }

        $response = $this->requestCaptchaFetch($challenge);
        if (($response['code'] ?? null) == 10000 && is_array($response['data'] ?? null)) {
            return [
                'state' => 'resolved',
                'data' => $response['data'],
                'message' => (string)($response['message'] ?? ''),
            ];
        }

        if (($response['code'] ?? null) == 10001) {
            return [
                'state' => 'pending',
                'data' => [],
                'message' => (string)($response['message'] ?? ''),
            ];
        }

        return [
            'state' => 'error',
            'data' => [],
            'message' => (string)($response['message'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestCaptchaFetch(string $challenge): array
    {
        return ApiCaptcha::fetch($challenge);
    }
}
