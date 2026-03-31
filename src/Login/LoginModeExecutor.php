<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Util\Exceptions\LoginException;

final class LoginModeExecutor
{
    /**
     * @param callable(string, string, string, string):array<string, mixed> $passwordLogin
     * @param callable(string, array<string, mixed>):void $completeLogin
     */
    public function executeAccount(
        string $username,
        string $password,
        string $validate,
        string $challenge,
        string $mode,
        callable $passwordLogin,
        callable $completeLogin,
    ): void {
        $response = $passwordLogin($username, $password, $validate, $challenge);
        $completeLogin($mode, $response);
    }

    /**
     * @param callable(string):bool $phoneValidator
     * @param callable(string, string):(?array<string, mixed>) $sendSms
     * @param callable(string):string $promptCode
     * @param callable(array<string, mixed>, string):void $completeSmsLogin
     */
    public function executeSms(
        string $username,
        string $countryCode,
        bool $validatePhone,
        callable $phoneValidator,
        callable $sendSms,
        callable $promptCode,
        callable $completeSmsLogin,
    ): void {
        if ($validatePhone && !$phoneValidator($username)) {
            throw new LoginException('当前用户名不是有效手机号格式', 6 * 3600);
        }

        $payload = $sendSms($username, $countryCode);
        if ($payload === null) {
            return;
        }

        $code = $promptCode('请输入收到的短信验证码: ');
        if ($code === '') {
            throw new LoginException('短信验证码不能为空', 3600);
        }

        $completeSmsLogin($payload, $code);
    }
}
