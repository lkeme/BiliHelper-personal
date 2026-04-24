<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Util\Exceptions\LoginException;

final class RiskVerificationContext
{
    public function __construct(
        public readonly string $verifyUrl,
        public readonly string $tmpToken,
        public readonly string $requestId = '',
        public readonly string $source = 'risk',
        public readonly string $hideTel = '',
        public readonly string $captchaKey = '',
    ) {
    }

    /**
     * @param array<string, mixed> $loginResponse
     */
    public static function fromLoginResponse(array $loginResponse): self
    {
        $verifyUrl = trim((string)($loginResponse['data']['url'] ?? ''));
        if ($verifyUrl === '') {
            throw new LoginException('风控验证链接缺失，无法继续手机号验证', 24 * 3600);
        }

        return self::fromVerifyUrl($verifyUrl);
    }

    public static function fromVerifyUrl(string $verifyUrl): self
    {
        $query = parse_url($verifyUrl, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            throw new LoginException('风控验证链接格式异常，无法解析参数', 24 * 3600);
        }

        parse_str($query, $params);
        $tmpToken = trim((string)($params['tmp_token'] ?? ''));
        $requestId = trim((string)($params['request_id'] ?? ''));
        $source = trim((string)($params['source'] ?? 'risk'));
        if ($tmpToken === '') {
            throw new LoginException('风控验证参数缺失 tmp_token，无法继续手机号验证', 24 * 3600);
        }

        return new self($verifyUrl, $tmpToken, $requestId, $source);
    }

    public function withHideTel(string $hideTel): self
    {
        return new self(
            $this->verifyUrl,
            $this->tmpToken,
            $this->requestId,
            $this->source,
            $hideTel,
            $this->captchaKey,
        );
    }

    public function withCaptchaKey(string $captchaKey): self
    {
        return new self(
            $this->verifyUrl,
            $this->tmpToken,
            $this->requestId,
            $this->source,
            $this->hideTel,
            $captchaKey,
        );
    }
}
