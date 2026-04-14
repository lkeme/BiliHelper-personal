<?php declare(strict_types=1);

namespace Bhp\Login;

final class LoginCookieMaintenanceService
{
    /**
     * 初始化 LoginCookieMaintenanceService
     * @param LoginCookiePatchService $cookiePatchService
     */
    public function __construct(
        private readonly LoginCookiePatchService $cookiePatchService,
    ) {
    }

    /**
     * @param array<string, mixed> $response
     * @return array{level: string, message: string, patched_cookie: ?string}
     */
    public function patchPcCookie(string $pcCookie, array $response): array
    {
        if (str_contains($pcCookie, 'buvid3')) {
            return [
                'level' => 'info',
                'message' => 'CookiePatch: buvid3已存在',
                'patched_cookie' => null,
            ];
        }

        $patchedCookie = $this->cookiePatchService->buildPatchedCookie($pcCookie, $response);
        if ($patchedCookie !== null) {
            return [
                'level' => 'notice',
                'message' => 'CookiePatch: buvid3已获取',
                'patched_cookie' => $patchedCookie,
            ];
        }

        $headers = $this->cookiePatchService->extractSetCookieHeaders($response);
        if ($headers === []) {
            return [
                'level' => 'warning',
                'message' => 'CookiePatch: 响应中未找到 Set-Cookie，跳过Cookie补丁',
                'patched_cookie' => null,
            ];
        }

        return [
            'level' => 'warning',
            'message' => 'CookiePatch: 本次未获取到buvid3',
            'patched_cookie' => null,
        ];
    }
}
