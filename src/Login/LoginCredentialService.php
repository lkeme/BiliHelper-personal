<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Passport\ApiOauth2;
use Bhp\Log\Log;
use Bhp\Runtime\AppContext;
use Bhp\Util\Exceptions\LoginException;

class LoginCredentialService
{
    public function __construct(protected AppContext $context)
    {
    }

    /**
     * @return array{username:string,password:string}
     */
    public function resolveCredentials(int $modeId): array
    {
        $username = (string)$this->context->config('login_account.username');
        $password = (string)$this->context->config('login_account.password');

        switch ($modeId) {
            case 1:
                if ($username === '' || $password === '') {
                    throw new LoginException('空白的帐号和口令', 6 * 3600);
                }
                break;
            case 2:
                if ($username === '') {
                    throw new LoginException('空白的帐号', 6 * 3600);
                }
                break;
            default:
                break;
        }

        return [
            'username' => $username,
            'password' => $this->encryptPassword($password),
        ];
    }

    public function encryptPassword(string $plaintext): string
    {
        Log::info('正在载入公钥');
        $response = ApiOauth2::getKey();
        if (isset($response['code']) && $response['code']) {
            throw new LoginException('公钥载入失败: ' . $response['message'], 600);
        }

        Log::info('公钥载入完毕');
        $publicKey = $response['data']['key'];
        $hash = $response['data']['hash'];
        openssl_public_encrypt($hash . $plaintext, $crypt, $publicKey);

        return base64_encode($crypt);
    }
}
