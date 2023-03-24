<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\XLive\LotteryInterface\V2;

use Bhp\Request\Request;

class ApiBox
{
    /**
     * 抽奖
     * @param int $aid
     * @param int $round
     * @return array
     */
    public static function draw(int $aid, int $round): array
    {
        // $url = 'https://api.live.bilibili.com/lottery/v1/Box/draw';
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v2/Box/draw';
        $payload = [
            'aid' => $aid,
            'number' => $round,
        ];
        return Request::getJson(true, 'pc', $url, $payload);
    }


    /**
     * 获取状态
     * @param int $aid
     * @return array
     */
    public static function getStatus(int $aid): array
    {
        // $url = 'https://api.live.bilibili.com/lottery/v1/box/getStatus';
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v2/Box/getStatus';
        $payload = [
            'aid' => $aid,
        ];
        // {"code":0,"data":null,"message":"ok","msg":"ok"}
        // {"code":0,"data":{"title":"荣耀宝箱抽奖","rule":"a 抽奖时间按如下规则抽取一次，重复无效。\nb 获奖者需要再获奖名单公布后一周内反馈姓名、邮寄地址、联系方式，因获奖者逾期查看获奖名单、逾期提交个人资料或个人资料有误，将视为自动放弃获奖资格及由此产生的权利。","current_round":2,"typeB":[{"startTime":"2020-05-18 18:30:00","imgUrl":"https://i0.hdslb.com/bfs/live/f600b89f2c2550b600612feba90e39901a9f027c.jpg","join_start_time":1589796000,"join_end_time":1589797800,"status":4,"list":[{"jp_name":"荣耀路由3","jp_num":"1","jp_id":3181,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/f600b89f2c2550b600612feba90e39901a9f027c.jpg"}],"round_num":1},{"startTime":"2020-05-18 19:00:00","imgUrl":"https://i0.hdslb.com/bfs/live/f600b89f2c2550b600612feba90e39901a9f027c.jpg","join_start_time":1589797800,"join_end_time":1589799600,"status":0,"list":[{"jp_name":"荣耀路由3","jp_num":"1","jp_id":3182,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/f600b89f2c2550b600612feba90e39901a9f027c.jpg"}],"round_num":2},{"startTime":"2020-05-18 19:30:00","imgUrl":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg","join_start_time":1589799600,"join_end_time":1589801400,"status":-1,"list":[{"jp_name":"荣耀平板V6","jp_num":"1","jp_id":3183,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg"}],"round_num":3},{"startTime":"2020-05-18 20:00:00","imgUrl":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg","join_start_time":1589801400,"join_end_time":1589803200,"status":-1,"list":[{"jp_name":"荣耀平板V6","jp_num":"1","jp_id":3184,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg"}],"round_num":4},{"startTime":"2020-05-18 20:30:00","imgUrl":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg","join_start_time":1589803200,"join_end_time":1589805000,"status":-1,"list":[{"jp_name":"荣耀平板V6","jp_num":"1","jp_id":3185,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg"}],"round_num":5},{"startTime":"2020-05-18 21:00:00","imgUrl":"https://i0.hdslb.com/bfs/live/73db69bd5a9e5dedb7d2f32d72fd6248b860e238.jpg","join_start_time":1589805000,"join_end_time":1589806800,"status":-1,"list":[{"jp_name":"荣耀MagicBook Pro","jp_num":"1","jp_id":3186,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/73db69bd5a9e5dedb7d2f32d72fd6248b860e238.jpg"}],"round_num":6},{"startTime":"2020-05-18 22:00:00","imgUrl":"https://i0.hdslb.com/bfs/live/4dba1e8b58c174d5e2311de339b1e02a3ac77a98.jpg","join_start_time":1589806800,"join_end_time":1589810400,"status":-1,"list":[{"jp_name":"荣耀智慧屏新品，荣耀MagicBook Pro，荣耀平板V6，荣耀路由3","jp_num":"1","jp_id":3187,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/4dba1e8b58c174d5e2311de339b1e02a3ac77a98.jpg"}],"round_num":7}],"activity_pic":"https://i0.hdslb.com/bfs/live/c3ed87683f6e87d256d1f5fdddbfb220fc4c2cdf.png","activity_id":556,"weight":20,"background":"https://i0.hdslb.com/bfs/live/84cd59bcb1e977359df618dbeb0f7828751f457c.png","title_color":"#FFFFFF","closeable":0,"jump_url":"https://live.bilibili.com/p/html/live-app-treasurebox/index.html?is_live_half_webview=1\u0026hybrid_biz=live-app-treasurebox\u0026hybrid_rotate_d=1\u0026hybrid_half_ui=1,3,100p,70p,0,0,30,100;2,2,375,100p,0,0,30,100;3,3,100p,70p,0,0,30,100;4,2,375,100p,0,0,30,100;5,3,100p,70p,0,0,30,100;6,3,100p,70p,0,0,30,100;7,3,100p,70p,0,0,30,100\u0026aid=556"},"message":"","msg":""}
        return Request::getJson(true, 'pc', $url, $payload);
    }
}

