<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Response\SmsSendResult;
use Bhp\Api\Passport\ApiLogin;
use Bhp\Runtime\AppContext;
use Bhp\Util\Exceptions\LoginException;

class LoginSmsService
{
    public function __construct(protected AppContext $context)
    {
    }

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

        throw new LoginException("短信验证码发送失败 {$raw}", 600);
    }

    protected function requestSendSms(array $payload): string
    {
        return ApiLogin::sendSms($payload);
    }
}
