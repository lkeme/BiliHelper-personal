<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class SmsSendResult
{
    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        public readonly array $payload,
        public readonly string $recaptchaUrl = '',
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function sent(array $payload): self
    {
        return new self($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function captchaRequired(string $recaptchaUrl, array $payload): self
    {
        return new self($payload, $recaptchaUrl);
    }

    /**
     * 处理requires验证码
     * @return bool
     */
    public function requiresCaptcha(): bool
    {
        return $this->recaptchaUrl !== '';
    }

    /**
     * 处理验证码键
     * @return string
     */
    public function captchaKey(): string
    {
        return (string)($this->payload['captcha_key'] ?? '');
    }
}
