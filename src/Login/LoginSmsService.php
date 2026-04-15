<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Response\SmsSendResult;
use Bhp\Api\Passport\ApiLogin;
use Bhp\Runtime\AppContext;
use Bhp\Util\Exceptions\LoginException;

class LoginSmsService
{
    /**
     * 初始化 LoginSmsService
     * @param AppContext $context
     * @param ApiLogin $apiLogin
     */
    public function __construct(
        protected AppContext $context,
        private readonly ApiLogin $apiLogin,
    )
    {
    }

    /**
     * 处理send短信
     * @param string $phone
     * @param string $cid
     * @param string $validate
     * @param string $challenge
     * @param string $recaptchaToken
     * @return SmsSendResult
     */
    public function sendSms(
        string $phone,
        string $cid,
        string $validate = '',
        string $challenge = '',
        string $recaptchaToken = '',
    ): SmsSendResult {
        $payload = [
            'cid' => $cid,
            'tel' => $phone,
            'statistics' => $this->context->device('app.bili_a.statistics'),
        ];
        if ($validate !== '' && $challenge !== '') {
            $payload['recaptcha_token'] = $recaptchaToken;
            $payload['gee_validate'] = $validate;
            $payload['gee_challenge'] = $challenge;
            $payload['gee_seccode'] = "$validate|jordan";
        }

        $raw = $this->requestSendSms($payload);
        $response = json_decode($raw, true);
        if (($response['code'] ?? null) == 0 && isset($response['data']['captcha_key']) && ($response['data']['recaptcha_url'] ?? '') === '') {
            $payload['captcha_key'] = $response['data']['captcha_key'];
            return SmsSendResult::sent($payload);
        }

        if (($response['code'] ?? null) == 0 && isset($response['data']['recaptcha_url']) && $response['data']['recaptcha_url'] !== '') {
            return SmsSendResult::captchaRequired((string)$response['data']['recaptcha_url'], $payload);
        }

        if (($response['code'] ?? null) == -105 && isset($response['data']['url']) && $response['data']['url'] !== '') {
            return SmsSendResult::captchaRequired((string)$response['data']['url'], $payload);
        }

        throw new LoginException("短信验证码发送失败 {$raw}", 600);
    }

    /**
     * 请求Send短信
     * @param array $payload
     * @return string
     */
    protected function requestSendSms(array $payload): string
    {
        return $this->apiLogin->sendSms($payload);
    }
}
