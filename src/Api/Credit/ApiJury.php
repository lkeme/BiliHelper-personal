<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2024 ~ 2025
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\Credit;

use Bhp\Request\Request;
use Bhp\User\User;

class ApiJury
{

    /**
     * 风纪委员状态
     * @return array
     */
    public static function jury(): array
    {
        $url = 'https://api.bilibili.com/x/credit/v2/jury/jury';
        $payload = [];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ];
        // {"code":25005,"message":"请成为風機委員后再试","ttl":1}
        // {"code":0,"message":"0","ttl":1,"data":{"uname":"","face":"http://i2.hdslb.com/bfs/face/.jpg","case_total":,"term_end":,"status":1}}
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * 申請連任
     * @return array
     */
    public static function juryApply(): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/credit/v2/jury/apply';
        $payload = [
            "csrf" => $user['csrf'],
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ];
        // {"code":0,"message":"0","ttl":1}
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * 獲取案件任務
     * @return array
     */
    public static function caseNext(): array
    {
        $url = 'https://api.bilibili.com/x/credit/v2/jury/case/next';
        $payload = [];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"case_id":"AC1xx411c7At"}}
        // {"code":25008,"message":"真给力 , 移交众裁的举报案件已经被处理完了","ttl":1}
        // {"code":25014,"message":"25014","ttl":1}
        // {"code":25005,"message":"请成为風機委員后再试","ttl":1}
        // {"code":25006,"message":"風機委員资格已过期","ttl":1}
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * 案件信息
     * @param string $case_id
     * @return array
     */
    public static function caseInfo(string $case_id): array
    {
        $url = 'https://api.bilibili.com/x/credit/v2/jury/case/info';
        $payload = [
            'case_id' => $case_id,
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"case_id":"","case_type":1,"vote_items":[{"vote":1,"vote_text":"合适"},{"vote":2,"vote_text":"一般"},{"vote":3,"vote_text":"不合适"},{"vote":4,"vote_text":"无法判断"}],"default_vote":4,"status":0,"origin_start":0,"avid":,"cid":,"vote_cd":5,"case_info":{"comment":{"uname":"用户1","face":"xxxx"},"danmu_img":""}}}
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * 衆議觀點
     * @param string $case_id
     * @param int $pn
     * @param int $ps
     * @return array
     */
    public static function caseOpinion(string $case_id, int $pn = 1, int $ps = 20): array
    {
        $url = 'https://api.bilibili.com/x/credit/v2/jury/case/opinion';
        $payload = [
            'case_id' => $case_id,
            'pn' => $pn,
            'ps' => $ps
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"total":438,"list":[]}}
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * 投票
     * @param string $case_id
     * @param int $vote
     * @param string $content
     * @param int $anonymous
     * @param int $insiders
     * @return array
     */
    public static function vote(string $case_id, int $vote, string $content = '', int $anonymous = 0, int $insiders = 0): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/credit/v2/jury/vote';
        $payload = [
            'case_id' => $case_id, # 案件id
            'vote' => $vote, # 投票选项
            'content' => $content, # 内容
            'anonymous' => $anonymous,  # 是否匿名
            'insiders' => $insiders,  # 是否观看
            'csrf' => $user['csrf'], # 验证
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ];
        // {"code":0,"message":"0","ttl":1}
        // {"code":25018,"message":"不能进行此操作","ttl":1}
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * 案件列表
     * @param int $pn
     * @param int $ps
     * @return array
     */
    public static function caseList(int $pn = 1, int $ps = 25): array
    {
        $url = 'https://api.bilibili.com/x/credit/jury/caseList';
        $payload = [
            'pn' => $pn,
            'ps' => $ps,
            '_' => time()
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => "https://www.bilibili.com/judgement/index"
        ];
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }

}
