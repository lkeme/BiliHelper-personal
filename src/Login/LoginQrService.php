<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Response\QrAuthCode;
use Bhp\Api\Response\QrPollResult;
use Bhp\Api\PassportTv\ApiQrcode;
use Bhp\Util\Exceptions\LoginException;

class LoginQrService
{
    public function fetchAuthCode(): QrAuthCode
    {
        $response = $this->requestAuthCode();
        if (($response['code'] ?? null) !== 0) {
            throw new LoginException('获取AuthCode错误: ' . ($response['message'] ?? ''), 600);
        }

        return QrAuthCode::fromArray(is_array($response['data'] ?? null) ? $response['data'] : []);
    }

    public function pollAuthCode(string $authCode): QrPollResult
    {
        $response = $this->requestPoll($authCode);

        return match ($response['code']) {
            0 => QrPollResult::confirmed((string)$response['message'], $response),
            86039 => QrPollResult::pending((string)$response['message']),
            -3, -400 => throw new LoginException("扫码失败 {$response['message']}", 600),
            86038 => throw new LoginException("扫码失败 {$response['message']}", 300),
            default => throw new LoginException("扫码失败 {$response['message']}", 600),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestAuthCode(): array
    {
        return ApiQrcode::authCode();
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestPoll(string $authCode): array
    {
        return ApiQrcode::poll($authCode);
    }
}
