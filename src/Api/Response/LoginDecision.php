<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class LoginDecision
{
    /**
     * 初始化 LoginDecision
     * @param string $action
     * @param string $message
     * @param string $captchaUrl
     * @param int $retryAfterSeconds
     */
    private function __construct(
        public readonly string $action,
        public readonly string $message = '',
        public readonly string $captchaUrl = '',
        public readonly int $retryAfterSeconds = 0,
    ) {
    }

    /**
     * 处理success
     * @param string $message
     * @return self
     */
    public static function success(string $message): self
    {
        return new self('success', $message);
    }

    /**
     * 处理验证码Required
     * @param string $captchaUrl
     * @param string $message
     * @return self
     */
    public static function captchaRequired(string $captchaUrl, string $message = '此次请求需要行为验证码'): self
    {
        return new self('captcha_required', $message, $captchaUrl);
    }

    /**
     * 处理风控验证Required
     * @param string $message
     * @return self
     */
    public static function riskVerificationRequired(string $message = '此次登录需要手机号安全验证'): self
    {
        return new self('risk_verification_required', $message);
    }

    /**
     * 处理失败
     * @param string $message
     * @param int $retryAfterSeconds
     * @return self
     */
    public static function failure(string $message, int $retryAfterSeconds): self
    {
        return new self('failure', $message, '', $retryAfterSeconds);
    }

    /**
     * 判断Success是否满足条件
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->action === 'success';
    }

    /**
     * 处理requires验证码
     * @return bool
     */
    public function requiresCaptcha(): bool
    {
        return $this->action === 'captcha_required';
    }

    /**
     * 判断requires风控验证
     * @return bool
     */
    public function requiresRiskVerification(): bool
    {
        return $this->action === 'risk_verification_required';
    }

    /**
     * 判断失败是否满足条件
     * @return bool
     */
    public function isFailure(): bool
    {
        return $this->action === 'failure';
    }
}
