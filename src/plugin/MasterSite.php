<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class MasterSite
{
    use TimeLock;

    public static function run()
    {
        if (self::getLock() > time() || getenv('USE_MASTER_SITE') == 'false') {
            return;
        }
        if (self::watchAid() && self::shareAid() && self::coinAdd()) {
            self::setLock(self::timing(10));
            return;
        }
        self::setLock(3600);
    }


    /**
     * @use 投币
     * @param $aid
     * @return bool
     */
    private static function reward($aid): bool
    {
        $user_info = User::parseCookies();
        $url = "https://api.bilibili.com/x/web-interface/coin/add";
        $payload = [
            "aid" => $aid,
            "multiply" => "1",
            "cross_domain" => "true",
            "csrf" => $user_info['token']
        ];
        $headers = [
            'Host' => "api.bilibili.com",
            'Origin' => "https://www.bilibili.com",
            'Referer' => "https://www.bilibili.com/video/av{$aid}",
            'User-Agent' => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.81 Safari/537.36",
        ];
        $raw = Curl::post('app', $url, Sign::common($payload), $headers);
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0) {
            Log::notice("主站任务: av{$aid}投币成功!");
            return true;
        } else {
            Log::warning("主站任务: av{$aid}投币失败!");
            return false;
        }
    }


    /**
     * @use 投币日志
     * @return int
     */
    protected static function coinLog(): int
    {
        $url = "https://api.bilibili.com/x/member/web/coin/log";
        $payload = [];
        $raw = Curl::get('pc', $url, $payload);
        $de_raw = json_decode($raw, true);

        $logs = isset($de_raw['data']['list']) ? $de_raw['data']['list'] : [];
        $coins = 0;
        foreach ($logs as $log) {
            $log_ux = strtotime($log['time']);
            $log_date = date('Y-m-d', $log_ux);
            $now_date = date('Y-m-d');
            if ($log_date != $now_date) {
                break;
            }
            if (strpos($log['reason'], "打赏") !== false) {
                switch ($log['delta']) {
                    case -1:
                        $coins += 1;
                        break;
                    case -2:
                        $coins += 2;
                        break;
                    default:
                        break;
                }
            }
        }
        return $coins;
    }

    /**
     * @use 视频投币
     * @return bool
     */
    protected static function coinAdd(): bool
    {
        switch (getenv('USE_ADD_COIN')) {
            case 'false':
                break;
            case 'true':
                // 预计数量 失败默认0  避免损失
                $estimate_num = intval(getenv('ADD_COIN_NUM') ?? 0);
                // 库存数量
                $stock_num = self::getCoin();
                // 实际数量 处理硬币库存少于预计数量
                $actual_num = intval($estimate_num > $stock_num ? $stock_num : $estimate_num) - self::coinLog();
                Log::info("当前硬币库存 {$stock_num} 预计投币 {$estimate_num} 实际投币 {$actual_num}");
                // 上限
                if ($actual_num <= 0) {
                    Log::info('今日投币上限已满!');
                    break;
                }
                // 稿件列表
                if (gettype('ADD_COIN_MODE') =='random'){
                    // 随机热门稿件榜单
                    $aids = self::getDayRankingAids($actual_num);
                }else{
                    // 固定获取关注UP稿件榜单, 不足会随机补全
                    $aids = self::getFollowUpAids($actual_num);
                }
                Log::info("获取稿件列表: ". implode(" ",$aids));
                // 投币
                foreach ($aids as $aid) {
                    self::reward($aid);
                }
                break;
            default:
                Log::warning('当前视频投币设置不正确, 请检查配置文件!');
                die();
                break;
        }
        return true;
    }


    /**
     * @use 获取随机AID
     * @return string
     */
    private static function getRandomAid(): string
    {
        do {
            $url = "https://api.bilibili.com/x/web-interface/newlist";
            $payload = [
                'pn' => mt_rand(1, 1000),
                'ps' => 1,
            ];
            $raw = Curl::get('other', $url, $payload);
            $de_raw = json_decode($raw, true);
            // echo "getRandomAid " . count($de_raw['data']['archives']) . PHP_EOL;
            // $aid = array_rand($de_raw['data']['archives'])['aid'];
        } while (count($de_raw['data']['archives']) == 0);
        $aid = $de_raw['data']['archives'][0]['aid'];
        return (string)$aid;
    }


    /**
     * @use 获取关注UP稿件列表
     * @param int $num
     * @return array
     */
    private static function getFollowUpAids(int $num): array
    {
        $aids = [];
        $rand_nums = [];
        $url = 'https://api.vc.bilibili.com/dynamic_svr/v1/dynamic_svr/dynamic_new';
        $user_info = User::parseCookies();
        $payload = [
            'uid' => $user_info['uid'],
            'type_list' => '8,512,4097,4098,4099,4100,4101'
        ];
        $headers = [
            'origin' => 'https://t.bilibili.com',
            'referer' => 'https://t.bilibili.com/pages/nav/index_new'
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        foreach ($de_raw['data']['cards'] as $index => $card) {
            if ($index >= $num) {
                break;
            }
            array_push($aids, $card['desc']['rid']);
        }
        // 此处补全缺失
        if (count($aids) < $num) {
            $aids = array_merge($aids, self::getDayRankingAids($num - count($aids)));
        }
        return $aids;
    }


    /**
     * @use 获取榜单稿件列表
     * @param int $num
     * @return array
     */
    private static function getDayRankingAids(int $num): array
    {
        // day: 日榜1 三榜3 周榜7 月榜30
        $aids = [];
        $rand_nums = [];
        $url = "https://api.bilibili.com/x/web-interface/ranking";
        $payload = [
            'rid' => 0,
            'day' => 1,
            'type' => 1,
            'arc_type' => 0
        ];
        $raw = Curl::get('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        for ($i = 0; $i < $num; $i++) {
            while (true) {
                $rand_num = mt_rand(1, 99);
                if (in_array($rand_num, $rand_nums)) {
                    continue;
                } else {
                    array_push($rand_nums, $rand_num);
                    break;
                }
            }
            $aid = $de_raw['data']['list'][$rand_nums[$i]]['aid'];
            array_push($aids, $aid);
        }

        return $aids;
    }


    /**
     * @use 分享视频
     * @return bool
     */
    private static function shareAid(): bool
    {
        # aid = 稿件av号
        $url = "https://api.bilibili.com/x/web-interface/share/add";
        $av_info = self::parseAid();
        $user_info = User::parseCookies();
        $payload = [
            'aid' => $av_info['aid'],
            'jsonp' => "jsonp",
            'csrf' => $user_info['token'],
        ];
        $headers = [
            'Host' => "api.bilibili.com",
            'Origin' => "https://www.bilibili.com",
            'Referer' => "https://www.bilibili.com/video/av{$av_info['aid']}",
            'User-Agent' => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.81 Safari/537.36",
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0) {
            Log::notice("主站任务: av{$av_info['aid']}分享成功!");
            return true;
        } else {
            Log::warning("主站任务: av{$av_info['aid']}分享失败!");
            return false;
        }
    }


    /**
     * @use 观看视频
     * @return bool
     */
    private static function watchAid(): bool
    {
        $url = "https://api.bilibili.com/x/report/click/h5";
        $av_info = self::parseAid();
        $user_info = User::parseCookies();
        $payload = [
            'aid' => $av_info['aid'],
            'cid' => $av_info['cid'],
            'part' => 1,
            'did' => $user_info['sid'],
            'ftime' => time(),
            'jsonp' => "jsonp",
            'lv' => "",
            'mid' => $user_info['uid'],
            'csrf' => $user_info['token'],
            'stime' => time()
        ];

        $headers = [
            'Host' => "api.bilibili.com",
            'Origin' => "https://www.bilibili.com",
            'Referer' => "https://www.bilibili.com/video/av{$av_info['aid']}",
            'User-Agent' => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.81 Safari/537.36",
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);

        if ($de_raw['code'] == 0) {
            $url = "https://api.bilibili.com/x/report/web/heartbeat";
            $payload = [
                "aid" => $av_info['aid'],
                "cid" => $av_info['cid'],
                "mid" => $user_info['uid'],
                "csrf" => $user_info['token'],
                "jsonp" => "jsonp",
                "played_time" => "0",
                "realtime" => $av_info['duration'],
                "pause" => false,
                "dt" => "7",
                "play_type" => "1",
                'start_ts' => time()
            ];
            $raw = Curl::post('pc', $url, $payload, $headers);
            $de_raw = json_decode($raw, true);

            if ($de_raw['code'] == 0) {
                sleep(5);
                $payload['played_time'] = $av_info['duration'] - 1;
                $payload['play_type'] = 0;
                $payload['start_ts'] = time();
                $raw = Curl::post('pc', $url, $payload, $headers);
                $de_raw = json_decode($raw, true);
                if ($de_raw['code'] == 0) {
                    Log::notice("主站任务: av{$av_info['aid']}观看成功!");
                    return true;
                }
            }
        }
        Log::warning("主站任务: av{$av_info['aid']}观看失败!");
        return false;
    }


    /**
     * @use 解析AID到CID
     * @return array
     */
    private static function parseAid(): array
    {
        while (true) {
            $aid = self::getRandomAid();
            $url = "https://api.bilibili.com/x/web-interface/view";
            $payload = [
                'aid' => $aid
            ];
            $raw = Curl::get('other', $url, $payload);
            $de_raw = json_decode($raw, true);
            if ($de_raw['code'] != 0) {
                continue;
            } else {
                if (!array_key_exists('cid', $de_raw['data'])) {
                    continue;
                }
            }
            $cid = $de_raw['data']['cid'];
            $duration = $de_raw['data']['duration'];
            return [
                'aid' => $aid,
                'cid' => $cid,
                'duration' => $duration
            ];
        }
    }

    /**
     * @use 获取硬币数量
     * @return int
     */
    private static function getCoin(): int
    {
        $url = 'https://account.bilibili.com/site/getCoin';
        $payload = [];
        $headers = [
            'referer' => 'https://account.bilibili.com/account/coin',
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        // {"code":0,"status":true,"data":{"money":1707.9}}
        if ($de_raw['code'] == 0 && isset($de_raw['data']['money'])) {
            return floor($de_raw['data']['money']);
        }
        return 0;
    }

}