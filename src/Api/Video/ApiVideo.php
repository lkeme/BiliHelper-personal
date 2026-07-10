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

namespace Bhp\Api\Video;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiVideo extends AbstractApiClient
{
    private const REGION_FEED_MAX_REQUEST_COUNT = 15;

    /**
     * 新版频道 feed 的 from_region。旧国创频道已不走普通稿件 feed，合并到动画；旧生活频道合并到生活兴趣。
     */
    private const REGION_FEED_IDS = [
        1005, // 动画
        1005, // 国创
        1003, // 音乐
        1004, // 舞蹈
        1008, // 游戏
        1010, // 知识
        1012, // 科技数码
        1013, // 汽车
        1030, // 生活兴趣
        1020, // 美食
        1024, // 动物
        1007, // 鬼畜
        1014, // 时尚美妆
        1009, // 资讯
        1002, // 娱乐
        1001, // 影视
    ];

    /**
     * 初始化 ApiVideo
     * @param Request $request
     */
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @param int $pn
     * @param int $ps
     * @return array
     */
    public function newlist(int $pn, int $ps): array
    {
        $url = 'https://api.bilibili.com/x/web-interface/newlist';
        $payload = [
            'pn' => $pn,
            'ps' => $ps
        ];
        return $this->decodeGet('other', $url, $payload, [], 'video.newlist');
    }

    /**
     * 获取分区动态/首页推荐
     * @param int $ps
     * @return array
     */
    public function dynamicRegion(int $ps = 30): array
    {
        $targetCount = max(1, $ps);
        $archives = [];
        $seenArchiveIds = [];
        $lastResponse = null;
        $attempts = min(
            count(self::REGION_FEED_IDS),
            (int)ceil($targetCount / self::REGION_FEED_MAX_REQUEST_COUNT) + 2
        );

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $response = $this->regionFeedRcmd(min(
                self::REGION_FEED_MAX_REQUEST_COUNT,
                $targetCount - count($archives)
            ));
            $lastResponse = $response;
            if (($response['code'] ?? 0) !== 0) {
                if ($archives !== []) {
                    break;
                }

                continue;
            }

            $items = $response['data']['archives'] ?? null;
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $archiveId = $this->archiveIdentity($item);
                if ($archiveId !== '') {
                    if (isset($seenArchiveIds[$archiveId])) {
                        continue;
                    }

                    $seenArchiveIds[$archiveId] = true;
                }

                $archives[] = $item;
                if (count($archives) >= $targetCount) {
                    break 2;
                }
            }
        }

        if ($archives === []) {
            return is_array($lastResponse) ? $lastResponse : [
                'code' => -500,
                'message' => 'video.region_feed_rcmd 响应格式异常',
                'data' => [],
            ];
        }

        return [
            'code' => 0,
            'message' => 'OK',
            'data' => [
                'archives' => $archives,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function regionFeedRcmd(int $requestCount): array
    {
        $url = 'https://api.bilibili.com/x/web-interface/region/feed/rcmd';
        $payload = [
            'display_id' => 1,
            'request_cnt' => $this->normalizeRegionFeedRequestCount($requestCount),
            'from_region' => self::REGION_FEED_IDS[array_rand(self::REGION_FEED_IDS)],
            'device' => 'web',
            'plat' => 30,
            'web_location' => '333.40138',
        ];
        return $this->decodeGet('other', $url, $payload, [
            'referer' => 'https://www.bilibili.com/',
        ], 'video.region_feed_rcmd');
    }

    /**
     * 获取榜单稿件
     * @return array
     */
    public function ranking(): array
    {
        // day: 日榜1 三榜3 周榜7 月榜30
        $url = 'https://api.bilibili.com/x/web-interface/ranking';
        $payload = [
            'rid' => 0,
            'day' => 1,
            'type' => 1,
            'arc_type' => 0
        ];
        return $this->decodeGet('other', $url, $payload, [], 'video.ranking');
    }

    /**
     * 首页填充物 max=30
     * @param int $ps
     * @return array
     */
    public function topFeedRCMD(int $ps = 30): array
    {
        $url = 'https://api.bilibili.com/x/web-interface/wbi/index/top/feed/rcmd';
        $payload = [
            'ps' => $ps,
        ];
        return $this->decodeGet('other', $url, $payload, [], 'video.top_feed_rcmd');
    }

    private function normalizeRegionFeedRequestCount(int $count): int
    {
        return max(1, min(self::REGION_FEED_MAX_REQUEST_COUNT, $count));
    }

    /**
     * @param array<string, mixed> $archive
     */
    private function archiveIdentity(array $archive): string
    {
        foreach (['aid', 'id', 'bvid'] as $key) {
            if (isset($archive[$key]) && (is_int($archive[$key]) || is_string($archive[$key]))) {
                return (string)$archive[$key];
            }
        }

        return '';
    }
}
