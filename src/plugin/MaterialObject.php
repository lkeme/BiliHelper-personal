<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;
use BiliHelper\Util\FilterWords;

class MaterialObject
{
    use TimeLock;
    use FilterWords;

    private static array $invalid_aids = [];
    private static int $start_aid = 0;
    private static int $end_aid = 0;

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('live_box')) {
            return;
        }
        self::setPauseStatus();
        self::calcAid(880, 1080);
        $lottery_list = self::fetchLottery();
        self::drawLottery($lottery_list);
        self::setLock(mt_rand(6, 10) * 60);
    }

    /**
     * @use 过滤抽奖Title
     * @param string $title
     * @return bool
     */
    private static function filterTitleWords(string $title): bool
    {
        self::loadJsonData();
        $sensitive_words = self::$store->get("MaterialObject.sensitive");
        foreach ($sensitive_words as $word) {
            if (str_contains($title, $word)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @use 抽奖盒子状态
     * @param int $aid
     * @param string $reply
     * @return mixed
     */
    private static function boxStatus(int $aid, string $reply = 'bool'): mixed
    {
        // $url = 'https://api.live.bilibili.com/lottery/v1/box/getStatus';
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v2/Box/getStatus';
        $payload = [
            'aid' => $aid,
        ];
        $raw = Curl::get('pc', $url, $payload);
        $de_raw = json_decode($raw, true);
        // {"code":0,"data":null,"message":"ok","msg":"ok"}
        // {"code":0,"data":{"title":"荣耀宝箱抽奖","rule":"a 抽奖时间按如下规则抽取一次，重复无效。\nb 获奖者需要再获奖名单公布后一周内反馈姓名、邮寄地址、联系方式，因获奖者逾期查看获奖名单、逾期提交个人资料或个人资料有误，将视为自动放弃获奖资格及由此产生的权利。","current_round":2,"typeB":[{"startTime":"2020-05-18 18:30:00","imgUrl":"https://i0.hdslb.com/bfs/live/f600b89f2c2550b600612feba90e39901a9f027c.jpg","join_start_time":1589796000,"join_end_time":1589797800,"status":4,"list":[{"jp_name":"荣耀路由3","jp_num":"1","jp_id":3181,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/f600b89f2c2550b600612feba90e39901a9f027c.jpg"}],"round_num":1},{"startTime":"2020-05-18 19:00:00","imgUrl":"https://i0.hdslb.com/bfs/live/f600b89f2c2550b600612feba90e39901a9f027c.jpg","join_start_time":1589797800,"join_end_time":1589799600,"status":0,"list":[{"jp_name":"荣耀路由3","jp_num":"1","jp_id":3182,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/f600b89f2c2550b600612feba90e39901a9f027c.jpg"}],"round_num":2},{"startTime":"2020-05-18 19:30:00","imgUrl":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg","join_start_time":1589799600,"join_end_time":1589801400,"status":-1,"list":[{"jp_name":"荣耀平板V6","jp_num":"1","jp_id":3183,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg"}],"round_num":3},{"startTime":"2020-05-18 20:00:00","imgUrl":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg","join_start_time":1589801400,"join_end_time":1589803200,"status":-1,"list":[{"jp_name":"荣耀平板V6","jp_num":"1","jp_id":3184,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg"}],"round_num":4},{"startTime":"2020-05-18 20:30:00","imgUrl":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg","join_start_time":1589803200,"join_end_time":1589805000,"status":-1,"list":[{"jp_name":"荣耀平板V6","jp_num":"1","jp_id":3185,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg"}],"round_num":5},{"startTime":"2020-05-18 21:00:00","imgUrl":"https://i0.hdslb.com/bfs/live/73db69bd5a9e5dedb7d2f32d72fd6248b860e238.jpg","join_start_time":1589805000,"join_end_time":1589806800,"status":-1,"list":[{"jp_name":"荣耀MagicBook Pro","jp_num":"1","jp_id":3186,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/73db69bd5a9e5dedb7d2f32d72fd6248b860e238.jpg"}],"round_num":6},{"startTime":"2020-05-18 22:00:00","imgUrl":"https://i0.hdslb.com/bfs/live/4dba1e8b58c174d5e2311de339b1e02a3ac77a98.jpg","join_start_time":1589806800,"join_end_time":1589810400,"status":-1,"list":[{"jp_name":"荣耀智慧屏新品，荣耀MagicBook Pro，荣耀平板V6，荣耀路由3","jp_num":"1","jp_id":3187,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/4dba1e8b58c174d5e2311de339b1e02a3ac77a98.jpg"}],"round_num":7}],"activity_pic":"https://i0.hdslb.com/bfs/live/c3ed87683f6e87d256d1f5fdddbfb220fc4c2cdf.png","activity_id":556,"weight":20,"background":"https://i0.hdslb.com/bfs/live/84cd59bcb1e977359df618dbeb0f7828751f457c.png","title_color":"#FFFFFF","closeable":0,"jump_url":"https://live.bilibili.com/p/html/live-app-treasurebox/index.html?is_live_half_webview=1\u0026hybrid_biz=live-app-treasurebox\u0026hybrid_rotate_d=1\u0026hybrid_half_ui=1,3,100p,70p,0,0,30,100;2,2,375,100p,0,0,30,100;3,3,100p,70p,0,0,30,100;4,2,375,100p,0,0,30,100;5,3,100p,70p,0,0,30,100;6,3,100p,70p,0,0,30,100;7,3,100p,70p,0,0,30,100\u0026aid=556"},"message":"","msg":""}
        switch ($reply) {
            // 等于0是有抽奖返回false
            case 'bool':
                if (!is_null($de_raw['data'])) {
                    return false;
                }
                return true;
            case 'array':
                if (!is_null($de_raw['data'])) {
                    return $de_raw;
                }
                return [];
            default:
                return $de_raw;
        }
    }

    /**
     * @use 获取抽奖
     * @return array
     */
    private static function fetchLottery(): array
    {
        // 缓存开始 如果存在就赋值 否则默认值
        if ($temp = getCache('invalid_aids')) {
            self::$invalid_aids = $temp;
        }

        $lottery_list = [];
        $max_probe = 10;
        $probes = range(self::$start_aid, self::$end_aid);
        foreach ($probes as $probe_aid) {
            // 最大试探
            if ($max_probe == 0) break;
            // 无效列表
            if (in_array($probe_aid, self::$invalid_aids)) {
                continue;
            }
            // 试探
            $response = self::boxStatus($probe_aid, 'array');
            if (empty($response)) {
                $max_probe--;
                continue;
            }
            $rounds = $response['data']['typeB'];
            $last_round = end($rounds);
            // 最后抽奖轮次无效
            if ($last_round['join_end_time'] < time()) {
                self::$invalid_aids[] = $probe_aid;
                continue;
            }
            // 过滤敏感词
            $title = $response['data']['title'];
            if (self::filterTitleWords($title)) {
                self::$invalid_aids[] = $probe_aid;
                continue;
            }
            // 过滤抽奖轮次
            $round_num = self::filterRound($rounds);
            if ($round_num == 0) {
                continue;
            }
            $lottery_list[] = [
                'aid' => $probe_aid,
                'num' => $round_num,
            ];
        }
        // 缓存结束 需要的数据的放进缓存
        setCache('invalid_aids', self::$invalid_aids);

        return $lottery_list;
    }

    /**
     * @use 过滤轮次
     * @param array $rounds
     * @return int
     */
    private static function filterRound(array $rounds): int
    {
        foreach ($rounds as $round) {
            $join_start_time = $round['join_start_time'];
            $join_end_time = $round['join_end_time'];
            if ($join_end_time > time() && time() > $join_start_time) {
                $status = $round['status'];
                /*
                 * 3 结束 1 抽过 -1 未开启 0 可参与
                 */
                if ($status == 0) {
                    return $round['round_num'];
                }
            }
        }
        return 0;
    }

    /**
     * @use 抽奖
     * @param array $lottery_list
     * @return bool
     */
    private static function drawLottery(array $lottery_list): bool
    {
        foreach ($lottery_list as $lottery) {
            $aid = $lottery['aid'];
            $num = $lottery['num'];
            Log::notice("实物抽奖 $aid 轮次 $num 可参与抽奖~");
            // $url = 'https://api.live.bilibili.com/lottery/v1/Box/draw';
            $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v2/Box/draw';
            $payload = [
                'aid' => $aid,
                'number' => $num,
            ];
            $raw = Curl::get('pc', $url, $payload);
            $de_raw = json_decode($raw, true);
            if ($de_raw['code'] == 0) {
                Log::notice("实物抽奖 $aid 轮次 $num 参与抽奖成功~");
            } else {
                Log::notice("实物抽奖 $aid 轮次 $num {$de_raw['msg']}~");
            }
        }
        return true;
    }

    /**
     * @use 计算Aid
     * @param $min
     * @param $max
     * @return bool
     */
    private static function calcAid($min, $max): bool
    {
        // Todo 优化计算AID算法
        if (self::$end_aid != 0 && self::$start_aid != 0) {
            return false;
        }
        while (true) {
            $middle = round(($min + $max) / 2);
            if (self::boxStatus($middle)) {
                if (self::boxStatus($middle + mt_rand(0, 3))) {
                    $max = $middle;
                } else {
                    $min = $middle;
                }
            } else {
                $min = $middle;
            }
            if ($max - $min == 1) {
                break;
            }
        }
        self::$start_aid = $min - mt_rand(15, 30);
        self::$end_aid = $min + mt_rand(15, 30);
        Log::info("实物抽奖起始值[" . self::$start_aid . "]，结束值[" . self::$end_aid . "]");
        return true;
    }
}