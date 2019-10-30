<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;

class MasterSite
{
    public static $lock = 0;

    public static function run()
    {
        if (self::$lock > time() || getenv('USE_MASTER_SITE') == 'false') {
            return;
        }
        if (self::watchAid() && self::shareAid() && self::coinAdd()) {
            self::$lock = time() + 24 * 60 * 60;
            return;
        }
        self::$lock = time() + 3600;
    }

    // 投币
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
        $raw = Curl::post($url, Sign::api($payload), $headers);
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0) {
            Log::notice("主站任务: av{$aid}投币成功!");
            return true;
        } else {
            Log::warning("主站任务: av{$aid}投币失败!");
            return false;
        }
    }

    // 投币日志
    protected static function coinLog(): int
    {
        $url = "https://api.bilibili.com/x/member/web/coin/log";
        $payload = [];
        $raw = Curl::get($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);

        $logs = $de_raw['data']['list'];
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

    // 投币操作
    protected static function coinAdd(): bool
    {
        switch (getenv('USE_ADD_COIN')) {
            case 'false':
                break;
            case 'true':
                $av_num = getenv('ADD_COIN_AV_NUM');
                $av_num = (int)$av_num;
                if ($av_num == 0) {
                    Log::warning('当前视频投币设置不正确,请检查配置文件!');
                    die();
                }
                if ($av_num == 1) {
                    $aid = !empty(getenv('ADD_COIN_AV')) ? getenv('ADD_COIN_AV') : self::getRandomAid();
                    self::reward($aid);
                } else {
                    $coins = $av_num - self::coinLog();
                    if ($coins <= 0) {
                        Log::info('今日投币上限已满!');
                        break;
                    }
                    $aids = self::getDayRankingAids($av_num);
                    foreach ($aids as $aid) {
                        self::reward($aid);
                    }
                }
                break;
            default:
                Log::warning('当前视频投币设置不正确,请检查配置文件!');
                die();
                break;
        }
        return true;
    }

    // 获取随机AID
    private static function getRandomAid(): string
    {
        do {
            $page = mt_rand(1, 1000);
            $payload = [];
            $url = "https://api.bilibili.com/x/web-interface/newlist?&pn={$page}&ps=1";
            $raw = Curl::get($url, Sign::api($payload));
            $de_raw = json_decode($raw, true);
            // echo "getRandomAid " . count($de_raw['data']['archives']) . PHP_EOL;
            // $aid = array_rand($de_raw['data']['archives'])['aid'];
        } while (count($de_raw['data']['archives']) == 0);
        $aid = $de_raw['data']['archives'][0]['aid'];
        return (string)$aid;
    }

    // 日榜AID
    private static function getDayRankingAids($num): array
    {
        // day: 日榜1 三榜3 周榜7 月榜30
        $payload = [];
        $aids = [];
        $rand_nums = [];
        $url = "https://api.bilibili.com/x/web-interface/ranking?rid=0&day=1&type=1&arc_type=0";
        $raw = Curl::get($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);
        for ($i = 0; $i < $num; $i++) {
            while (true) {
                $rand_num = random_int(1, 100);
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

    // 分享视频
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
        $raw = Curl::post($url, Sign::api($payload), $headers);
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0) {
            Log::notice("主站任务: av{$av_info['aid']}分享成功!");
            return true;
        } else {
            Log::warning("主站任务: av{$av_info['aid']}分享失败!");
            return false;
        }
    }

    // 观看视频
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

        $raw = Curl::post($url, Sign::api($payload), $headers);
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
            $raw = Curl::post($url, Sign::api($payload), $headers);
            $de_raw = json_decode($raw, true);

            if ($de_raw['code'] == 0) {
                sleep(5);
                $payload['played_time'] = $av_info['duration'] - 1;
                $payload['play_type'] = 0;
                $payload['start_ts'] = time();
                $raw = Curl::post($url, Sign::api($payload), $headers);
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

    // 解析AID到CID
    private static function parseAid(): array
    {
        while (true) {
            $aid = self::getRandomAid();
            $url = "https://api.bilibili.com/x/web-interface/view?aid={$aid}";
            $raw = Curl::get($url);
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
            break;
        }

        return [
            'aid' => $aid,
            'cid' => $cid,
            'duration' => $duration
        ];
    }

}