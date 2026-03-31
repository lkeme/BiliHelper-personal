<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class LoginDecision
{
    private function __construct(
        public readonly string $action,
        public readonly string $message = '',
        public readonly string $captchaUrl = '',
        public readonly int $retryAfterSeconds = 0,
    ) {
    }

    public static function success(string $message): self
    {
        return new self('success', $message);
    }

    public static function captchaRequired(string $captchaUrl, string $message = '此次请求需要行为验证码'): self
    {
        return new self('captcha_required', $message, $captchaUrl);
    }

    public static function failure(string $message, int $retryAfterSeconds): self
    {
        return new self('failure', $message, '', $retryAfterSeconds);
    }

    public function isSuccess(): bool
    {
        return $this->action === 'success';
    }

    public function requiresCaptcha(): bool
    {
        return $this->action === 'captcha_required';
    }

    public function isFailure(): bool
    {
        return $this->action === 'failure';
    }
}
