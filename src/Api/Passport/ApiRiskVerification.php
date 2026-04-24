<?php declare(strict_types=1);

namespace Bhp\Api\Passport;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

final class ApiRiskVerification extends AbstractApiClient
{
    /**
     * 初始化 ApiRiskVerification
     * @param Request $request
     */
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * 获取安全中心绑定信息
     * @return array<string, mixed>
     */
    public function userInfo(string $tmpCode): array
    {
        return $this->decodeGet(
            'app',
            'https://api.bilibili.com/x/safecenter/user/info',
            ['tmp_code' => $tmpCode],
            [
                'Accept-Encoding' => 'identity',
            ],
            'risk.user_info',
        );
    }

    /**
     * 获取风控短信前置验证码参数
     * @return array<string, mixed>
     */
    public function preCaptcha(): array
    {
        return $this->decodePost(
            'app',
            'https://passport.bilibili.com/x/safecenter/captcha/pre',
            ['source' => 'main-fe'],
            [
                'Accept-Encoding' => 'identity',
            ],
            'risk.pre_captcha',
        );
    }

    /**
     * 发送风控验证短信
     * @return array<string, mixed>
     */
    public function sendSms(
        string $tmpCode,
        string $geeChallenge,
        string $geeValidate,
        string $geeSeccode,
        string $recaptchaToken,
        string $referer,
    ): array {
        $payload = [
            'sms_type' => 'loginTelCheck',
            'tmp_code' => $tmpCode,
            'gee_challenge' => $geeChallenge,
            'gee_validate' => $geeValidate,
            'gee_seccode' => $geeSeccode,
            'recaptcha_token' => $recaptchaToken,
        ];

        return $this->decodePost(
            'app',
            'https://passport.bilibili.com/x/safecenter/common/sms/send',
            $this->request()->signLoginPayload($payload),
            [
                'Accept-Encoding' => 'identity',
                'Referer' => $referer,
            ],
            'risk.send_sms',
        );
    }

    /**
     * 提交风控短信验证码
     * @return array<string, mixed>
     */
    public function verifySms(
        string $smsCode,
        string $tmpCode,
        string $requestId,
        string $captchaKey,
        string $source,
        string $referer,
    ): array {
        $payload = [
            'type' => 'loginTelCheck',
            'code' => $smsCode,
            'tmp_code' => $tmpCode,
            'request_id' => $requestId,
            'source' => $source,
            'captcha_key' => $captchaKey,
        ];

        return $this->decodePost(
            'app',
            'https://passport.bilibili.com/x/safecenter/login/tel/verify',
            $this->request()->signLoginPayload($payload),
            [
                'Accept-Encoding' => 'identity',
                'Referer' => $referer,
            ],
            'risk.verify_sms',
        );
    }

    /**
     * 用安全验证返回 code 换取 OAuth 登录信息
     * @return array<string, mixed>
     */
    public function oauth2AccessToken(string $code): array
    {
        $payload = [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'local_id' => $this->request()->buvidValue(),
        ];

        return $this->decodePost(
            'app',
            'https://passport.bilibili.com/x/passport-login/oauth2/access_token',
            $this->request()->signLoginPayload($payload),
            [
                'Accept-Encoding' => 'identity',
            ],
            'risk.oauth2_access_token',
        );
    }
}
