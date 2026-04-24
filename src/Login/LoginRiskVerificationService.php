<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Passport\ApiRiskVerification;
use Bhp\Util\Exceptions\LoginException;

final class LoginRiskVerificationService
{
    public function __construct(
        private readonly LoginCaptchaService $captchaService,
        private readonly ApiRiskVerification $apiRiskVerification,
    ) {
    }

    /**
     * @param array<string, mixed> $loginResponse
     * @param callable(string, string):string $requestCode
     * @param callable(string):void $info
     * @param callable(string):void $warning
     * @param callable(string):void $notice
     * @return array<string, mixed>
     */
    public function handle(
        array $loginResponse,
        callable $requestCode,
        callable $info,
        callable $warning,
        callable $notice,
    ): array {
        $context = RiskVerificationContext::fromLoginResponse($loginResponse);
        $context = $this->enrichUserInfo($context);

        $warning('账密登录触发安全风控，需完成绑定手机号验证');
        if ($context->hideTel !== '') {
            $info('将向绑定手机号发送验证码: ' . $context->hideTel);
        }

        $captchaData = $this->requestPreCaptcha();
        $info('已获取安全验证参数，准备进行行为验证码校验');
        $captcha = $this->waitForCaptchaResolution(
            (string)($captchaData['gee_gt'] ?? ''),
            (string)($captchaData['gee_challenge'] ?? ''),
            $warning,
            $info,
            $notice,
        );

        $context = $this->sendRiskSms(
            $context,
            (string)($captchaData['recaptcha_token'] ?? ''),
            (string)($captchaData['gee_challenge'] ?? ''),
            $captcha['validate'],
            (string)($captcha['seccode'] ?? $captcha['validate']),
        );
        $notice('短信验证码已发送，请查收');

        $code = trim($requestCode('请输入收到的短信验证码: ', $context->hideTel));
        if ($code === '') {
            throw new LoginException('短信验证码不能为空', 3600);
        }

        if ($context->requestId === '') {
            $warning('当前风控链接未提供 request_id，将尝试兼容模式提交短信验证码');
        }

        $info('正在校验短信验证码');
        $verifyResponse = $this->apiRiskVerification->verifySms(
            $code,
            $context->tmpToken,
            $context->requestId,
            $context->captchaKey,
            $context->source,
            $context->verifyUrl,
        );
        $verifyCode = trim((string)($verifyResponse['data']['code'] ?? ''));
        if (($verifyResponse['code'] ?? -1) !== 0 || $verifyCode === '') {
            $message = trim((string)($verifyResponse['message'] ?? '短信验证码校验失败'));
            throw new LoginException($message !== '' ? $message : '短信验证码校验失败', 600);
        }

        $notice('手机号验证通过，继续换取登录令牌');
        $info('正在换取 OAuth 登录令牌');

        return $this->apiRiskVerification->oauth2AccessToken($verifyCode);
    }

    private function enrichUserInfo(RiskVerificationContext $context): RiskVerificationContext
    {
        $response = $this->apiRiskVerification->userInfo($context->tmpToken);
        if (($response['code'] ?? -1) !== 0) {
            $message = trim((string)($response['message'] ?? '获取安全验证信息失败'));
            throw new LoginException($message !== '' ? $message : '获取安全验证信息失败', 600);
        }

        $accountInfo = is_array($response['data']['account_info'] ?? null)
            ? $response['data']['account_info']
            : [];
        if (!($accountInfo['tel_verify'] ?? false)) {
            throw new LoginException('当前账号未开启手机号验证，无法继续风控校验', 24 * 3600);
        }

        return $context->withHideTel(trim((string)($accountInfo['hide_tel'] ?? '')));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPreCaptcha(): array
    {
        $response = $this->apiRiskVerification->preCaptcha();
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $gt = trim((string)($data['gee_gt'] ?? ''));
        $challenge = trim((string)($data['gee_challenge'] ?? ''));
        $token = trim((string)($data['recaptcha_token'] ?? ''));
        if (($response['code'] ?? -1) !== 0 || $gt === '' || $challenge === '' || $token === '') {
            $message = trim((string)($response['message'] ?? '获取风控验证码参数失败'));
            throw new LoginException($message !== '' ? $message : '获取风控验证码参数失败', 600);
        }

        return $data;
    }

    /**
     * @param callable(string):void $warning
     * @param callable(string):void $info
     * @param callable(string):void $notice
     * @return array{validate:string,challenge:string}
     */
    private function waitForCaptchaResolution(
        string $gt,
        string $challenge,
        callable $warning,
        callable $info,
        callable $notice,
    ): array {
        if ($gt === '' || $challenge === '') {
            throw new LoginException('风控验证码参数缺失', 600);
        }

        $this->captchaService->assertCaptchaServiceReady();
        $manualCaptchaUrl = $this->captchaService->buildManualCaptchaUrl([
            'gt' => $gt,
            'challenge' => $challenge,
        ]);
        $expiresAt = time() + 120;
        $nextProgressLogAt = time() + 10;

        $warning('需要先完成行为验证码后才能发送短信');
        $info('验证码处理页: ' . $manualCaptchaUrl);
        $info('请在浏览器中打开验证码链接，完成验证后当前进程会继续执行，最长等待 120 秒');
        while (true) {
            try {
                $result = $this->captchaService->pollCaptchaResult($challenge, $expiresAt);
            } catch (LoginException $e) {
                if ($e->getMessage() === '验证码识别超时') {
                    throw new LoginException('行为验证码等待超时，本次运行结束，请重新开始登录流程', 300);
                }

                throw $e;
            }

            if ($result['state'] === 'resolved') {
                $notice('行为验证码处理成功，开始发送短信验证码');
                return $result['data'];
            }

            if ($result['state'] !== 'pending') {
                $detail = trim((string)($result['message'] ?? ''));
                throw new LoginException(
                    $detail !== '' ? '行为验证码处理失败: ' . $detail : '行为验证码处理失败',
                    300,
                );
            }

            $now = time();
            if ($now >= $nextProgressLogAt) {
                $remainingSeconds = max(0, $expiresAt - $now);
                $info(sprintf('仍在等待人工完成行为验证码，剩余约 %d 秒', $remainingSeconds));
                $nextProgressLogAt = $now + 10;
            }

            usleep(2_000_000);
        }
    }

    private function sendRiskSms(
        RiskVerificationContext $context,
        string $recaptchaToken,
        string $geeChallenge,
        string $validate,
        string $seccode,
    ): RiskVerificationContext {
        if ($recaptchaToken === '' || $geeChallenge === '' || $validate === '' || $seccode === '') {
            throw new LoginException('风控短信发送参数缺失', 600);
        }

        $response = $this->apiRiskVerification->sendSms(
            $context->tmpToken,
            $geeChallenge,
            $validate,
            $seccode,
            $recaptchaToken,
            $context->verifyUrl,
        );
        $captchaKey = trim((string)($response['data']['captcha_key'] ?? ''));
        if (($response['code'] ?? -1) !== 0 || $captchaKey === '') {
            $message = trim((string)($response['message'] ?? '发送短信验证码失败'));
            throw new LoginException($message !== '' ? $message : '发送短信验证码失败', 600);
        }

        return $context->withCaptchaKey($captchaKey);
    }
}
