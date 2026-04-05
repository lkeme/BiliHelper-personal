<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\Manga;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiManga
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ClockIn(): array
    {
        return $this->decodePost('app', 'https://manga.bilibili.com/twirp/activity.v1.Activity/ClockIn', $this->request->signCommonPayload([]), [], 'manga.clock_in');
    }

    /**
     * @return array<string, mixed>
     */
    public function ShareComic(): array
    {
        return $this->decodePost('app', 'https://manga.bilibili.com/twirp/activity.v1.Activity/ShareComic', $this->request->signCommonPayload([]), [], 'manga.share');
    }

    /**
     * @return array<string, mixed>
     */
    public function GetClockInInfo(): array
    {
        return $this->decodePost('app', 'https://manga.bilibili.com/twirp/activity.v1.Activity/GetClockInInfo', $this->request->signCommonPayload([]), [], 'manga.clock_in_info');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function decodePost(string $os, string $url, array $payload, array $headers, string $label): array
    {
        try {
            $raw = $this->request->postText($os, $url, $payload, $headers);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => "{$label} 请求失败: {$throwable->getMessage()}",
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, $label);
    }
}
