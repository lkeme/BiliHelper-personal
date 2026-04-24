<?php declare(strict_types=1);

namespace Bhp\Api\Passport;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiCaptcha extends AbstractApiClient
{
    /**
     * 初始化 ApiCaptcha
     * @param Request $request
     */
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function combine(int $plat = 3): array
    {
        return $this->decodeGet('other', 'https://passport.bilibili.com/web/captcha/combine', [
            'plat' => $plat,
        ], [], 'passport.captcha.combine');
    }

    /**
     * @param array<string, mixed> $captcha
     * @return array<string, mixed>
     */
    public function ocr(array $captcha): array
    {
        return $this->decodePostJson('other', 'https://captcha-v1.mudew.com:19951/', [
            'type' => 'gt3',
            'gt' => $captcha['gt'],
            'challenge' => $captcha['challenge'],
            'referer' => 'https://passport.bilibili.com/',
        ], [
            'Content-Type' => 'application/json',
        ], 'passport.captcha.ocr');
    }

    /**
     * @return array<string, mixed>
     */
    public function fetch(string $url, string $challenge): array
    {
        $url = rtrim($url, '/') . '/fetch';

        return $this->decodeGet('other', $url, [
            'challenge' => $challenge,
        ], [], 'captcha.fetch', null, 3);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function openManualFlow(string $url, array $payload): array
    {
        $url = rtrim($url, '/') . '/api/manual-flow/open';

        return $this->decodePost('other', $url, $payload, [], 'captcha.manual_flow.open', null, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchManualFlow(string $url, string $flowId): array
    {
        $url = rtrim($url, '/') . '/api/manual-flow';

        return $this->decodeGet('other', $url, [
            'id' => $flowId,
        ], [], 'captcha.manual_flow.fetch', null, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function clearManualFlow(string $url, string $flowId): array
    {
        $url = rtrim($url, '/') . '/api/manual-flow/clear';

        return $this->decodePost('other', $url, [
            'id' => $flowId,
        ], [], 'captcha.manual_flow.clear', null, 3);
    }
}
