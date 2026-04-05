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

namespace Bhp\Api\LinkGroup;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiLoveClub
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function myGroups(): array
    {
        return $this->decodeGet('app', 'https://api.vc.bilibili.com/link_group/v1/member/my_groups', $this->request->signCommonPayload([]), [], 'love_club.groups');
    }

    /**
     * @return array<string, mixed>
     */
    public function signIn(int $group_id, int $owner_id): array
    {
        return $this->decodeGet('app', 'https://api.vc.bilibili.com/link_setting/v1/link_setting/sign_in', $this->request->signCommonPayload([
            'group_id' => $group_id,
            'owner_id' => $owner_id,
        ]), [], 'love_club.sign_in');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function decodeGet(string $os, string $url, array $payload, array $headers, string $label): array
    {
        try {
            $raw = $this->request->getText($os, $url, $payload, $headers);
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
