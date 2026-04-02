<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Api\Passport\ApiOauth2;
use Bhp\Api\Response\LoginTokenBundle;
use Bhp\Log\Log;
use Bhp\Runtime\AppContext;
use Bhp\Util\Exceptions\LoginException;
use Bhp\Util\Exceptions\NoLoginException;

class LoginTokenLifecycleService
{
    public function __construct(
        protected AppContext $context,
        protected LoginCookiePatchService $cookiePatchService,
    ) {
    }

    public function ensureSessionAlive(
        string $token,
        string $refreshToken,
        callable $loginFallback,
        callable $patchCookie,
    ): bool {
        Log::info('检查登录令牌有效性');
        if (!$this->validateToken($token)) {
            Log::warning('登录令牌失效过期或需要保活');
            Log::info('申请更换登录令牌中...');
            if (!$this->refreshToken($token, $refreshToken)) {
                Log::warning('无效的登录令牌，尝试重新申请...');
                $loginFallback();
            }
        }

        $token = $this->context->auth('access_token');
        if (!$this->myInfo($token)) {
            throw new NoLoginException('登录信息校验失败');
        }
        $patchCookie();

        return true;
    }

    public function validateToken(string $token): bool
    {
        $response = $this->requestTokenInfo($token);
        $this->guardInfrastructureFailure($response, '检查令牌');
        if (isset($response['code']) && $response['code']) {
            Log::error('检查令牌失败', ['msg' => $response['message']]);
            return false;
        }

        Log::notice('令牌有效期: ' . date('Y-m-d H:i:s', time() + $response['data']['expires_in']));

        return !$response['data']['refresh'] && $response['data']['expires_in'] > 14400;
    }

    public function refreshToken(string $token, string $refreshToken): bool
    {
        $response = $this->requestTokenRefresh($token, $refreshToken);
        $this->guardInfrastructureFailure($response, '刷新令牌');
        if (isset($response['code']) && $response['code']) {
            Log::error('重新生成令牌失败', ['msg' => $response['message']]);
            return false;
        }

        Log::info('重新令牌生成完毕');
        $this->persistLoginResponse($response);
        Log::info('重置信息配置完毕');

        return true;
    }

    public function myInfo(string $token): bool
    {
        $response = $this->requestMyInfo($token);
        $this->guardInfrastructureFailure($response, '获取登录信息');
        if (isset($response['code']) && $response['code']) {
            Log::error('获取登录信息失败', ['msg' => $response['message']]);
            return false;
        }

        Log::info('登录信息 ' . $this->describeMyInfo($response, $this->requestNavStat($token)));
        return true;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function persistLoginResponse(array $data): void
    {
        $bundle = LoginTokenBundle::fromLoginResponse($data);
        $this->context->setAuth('access_token', $bundle->accessToken);
        $this->context->setAuth('refresh_token', $bundle->refreshToken);
        $this->context->setAuth('cookie', $bundle->cookie);
        $this->context->setAuth('pc_cookie', $bundle->cookie);
        $this->context->setAuth('uid', $bundle->uid);
        $this->context->setAuth('csrf', $bundle->csrf);
        $this->context->setAuth('sid', $bundle->sid);
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestTokenInfo(string $token): array
    {
        return ApiOauth2::tokenInfoNew($token);
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestTokenRefresh(string $token, string $refreshToken): array
    {
        return ApiOauth2::tokenRefreshNew($token, $refreshToken);
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestMyInfo(string $token): array
    {
        return ApiOauth2::myInfo($token);
    }

    /**
     * @param array<string, mixed> $response
     */
    protected function guardInfrastructureFailure(array $response, string $label): void
    {
        $code = (int)($response['code'] ?? 0);
        if ($code !== -500) {
            return;
        }

        $message = trim((string)($response['message'] ?? ''));
        throw new LoginException("{$label}请求失败: {$message}", 600);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function requestNavStat(string $token): ?array
    {
        $response = ApiOauth2::navStat($token);
        if (($response['code'] ?? -1) !== 0) {
            return null;
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $response
     * @param array<string, mixed>|null $navStat
     */
    protected function describeMyInfo(array $response, ?array $navStat = null): string
    {
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $vip = is_array($data['vip'] ?? null) ? $data['vip'] : [];
        $vipLabel = is_array($vip['label'] ?? null) ? $vip['label'] : [];

        $name = trim((string)($data['name'] ?? '-')) ?: '-';
        $level = isset($data['level']) && is_numeric($data['level']) ? 'Lv' . (string)$data['level'] : '-';
        $coins = isset($data['coins']) && is_numeric($data['coins']) ? $this->formatCoins((float)$data['coins']) . '币' : '-';
        $vipText = $this->describeVipLabel($vip, $vipLabel);
        $summary = sprintf('👤 %s · %s · 🪙 %s · %s', $name, $level, $coins, $vipText);
        $statText = $this->describeNavStat($navStat);
        if ($statText === '') {
            return $summary;
        }

        return $summary . ' · ' . $statText;
    }

    /**
     * @param array<string, mixed> $vip
     * @param array<string, mixed> $vipLabel
     */
    protected function describeVipLabel(array $vip, array $vipLabel): string
    {
        $label = trim((string)($vipLabel['text'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        if (((int)($vip['status'] ?? 0)) !== 1) {
            return '无';
        }

        return match ((int)($vip['type'] ?? 0)) {
            2 => '年度大会员',
            1 => '月度大会员',
            default => '大会员',
        };
    }

    protected function formatCoins(float $coins): string
    {
        if ((float)(int)$coins === $coins) {
            return (string)(int)$coins;
        }

        return rtrim(rtrim(sprintf('%.2f', $coins), '0'), '.');
    }

    /**
     * @param array<string, mixed>|null $navStat
     */
    protected function describeNavStat(?array $navStat): string
    {
        $data = is_array($navStat['data'] ?? null) ? $navStat['data'] : null;
        if ($data === null) {
            return '';
        }

        $parts = [];
        foreach ([
            'following' => '关注',
            'follower' => '粉丝',
            'dynamic_count' => '动态',
        ] as $key => $label) {
            if (!isset($data[$key]) || !is_numeric($data[$key])) {
                continue;
            }

            $parts[] = $label . (string)(int)$data[$key];
        }

        return implode(' · ', $parts);
    }
}
