<?php declare(strict_types=1);

namespace Bhp\Api\Passport;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiCaptcha
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function combine(int $plat = 3): array
    {
        try {
            $raw = $this->request->getText('other', 'https://passport.bilibili.com/web/captcha/combine', [
                'plat' => $plat,
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'passport.captcha.combine 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'passport.captcha.combine');
    }

    /**
     * @param array<string, mixed> $captcha
     * @return array<string, mixed>
     */
    public function ocr(array $captcha): array
    {
        try {
            $raw = $this->request->postJsonBodyText('other', 'https://captcha-v1.mudew.com:19951/', [
                'type' => 'gt3',
                'gt' => $captcha['gt'],
                'challenge' => $captcha['challenge'],
                'referer' => 'https://passport.bilibili.com/',
            ], [
                'Content-Type' => 'application/json',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'passport.captcha.ocr 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'passport.captcha.ocr');
    }

    /**
     * @return array<string, mixed>
     */
    public function fetch(string $url, string $challenge): array
    {
        $url = rtrim($url, '/') . '/fetch';

        try {
            $raw = $this->request->getText('other', $url, [
                'challenge' => $challenge,
            ], [], 3);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'captcha.fetch 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'captcha.fetch');
    }
}
