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

namespace Bhp\Api\Api\X\Relation;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiRelation extends AbstractApiClient
{
    public const ACTION_FOLLOW = 1;
    public const ACTION_UNFOLLOW = 2;

    public const SOURCE_DEFAULT = 11;
    public const SOURCE_CREATOR_INCENTIVE = 192;
    public const SOURCE_ACTIVITY_PAGE = 222;

    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * 关注列表
     * @param int $pn
     * @param int $ps
     * @param string $order
     * @param string $order_type
     * @return array
     */
    public function followings(int $pn = 1, int $ps = 20, string $order = 'desc', string $order_type = 'order_type'): array
    {
        $url = 'https://api.bilibili.com/x/relation/followings';
        $uid = $this->request()->uidValue();
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$uid}/fans/follow",
        ];
        $payload = [
            'vmid' => $uid,
            'pn' => $pn,
            'ps' => $ps,
            'order' => $order,
            'order_type' => $order_type,
        ];

        return $this->decodeGet('pc', $url, $payload, $headers, 'relation.followings');
    }


    /**
     * 获取关注分组列表
     * @return array
     */
    public function tags(): array
    {
        $uid = $this->request()->uidValue();
        $url = 'https://api.bilibili.com/x/relation/tags';
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$uid}/fans/follow",
        ];

        return $this->decodeGet('pc', $url, [], $headers, 'relation.tags');
    }

    /**
     * 获取关注分组列表
     * @param int $tag_id
     * @param int $pn
     * @param int $ps
     * @return array
     */
    public function tag(int $tag_id, int $pn = 1, int $ps = 20): array
    {
        $uid = $this->request()->uidValue();
        $url = 'https://api.bilibili.com/x/relation/tag';
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$uid}/fans/follow",
        ];
        $payload = [
            'mid' => $uid,
            'tagid' => $tag_id,
            'pn' => $pn,
            'ps' => $ps,
        ];

        return $this->decodeGet('pc', $url, $payload, $headers, 'relation.tag');
    }


    /**
     * 取关
     * @param int $uid
     * @return array
     */
    public function modify(int $uid): array
    {
        return $this->act($uid, self::ACTION_UNFOLLOW, self::SOURCE_DEFAULT);
    }

    /**
     * 关注
     * @param int $uid
     * @param int $source
     * @return array
     */
    public function follow(int $uid, int $source = self::SOURCE_ACTIVITY_PAGE): array
    {
        return $this->act($uid, self::ACTION_FOLLOW, $source);
    }

    /**
     * @param int $uid
     * @param int $act
     * @param int $source
     * @return array
     */
    protected function act(int $uid, int $act, int $source): array
    {
        $currentUid = $this->request()->uidValue();
        $url = 'https://api.bilibili.com/x/relation/modify';
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$currentUid}/fans/follow",
        ];
        $payload = [
            'fid' => $uid,
            'act' => $act,
            're_src' => $source,
            'csrf' => $this->request()->csrfValue(),
        ];

        return $this->decodePost('pc', $url, $payload, $headers, 'relation.modify');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */}
