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

class Judge
{
    // https://www.bilibili.com/judgement/index

    // 评论 case_type: 1 default_vote: 4
    // 0: {vote: 1, vote_text: "合适"}
    // 1: {vote: 2, vote_text: "一般"}
    // 2: {vote: 3, vote_text: "不合适"}
    // 3: {vote: 4, vote_text: "无法判断"}

    // 弹幕氛围 case_type: 4 default_vote: 14
    // 0: {vote: 11, vote_text: "好"}
    // 1: {vote: 12, vote_text: "普通"}
    // 2: {vote: 13, vote_text: "差"}
    // 3: {vote: 14, vote_text: "无法判断"}


    use TimeLock;

    private static array $default_headers = [
        'origin' => 'https://www.bilibili.com',
        'referer' => 'https://www.bilibili.com/',
    ];

    private static array $wait_case = [];

    /**
     * @use run
     */
    public static function run(): void
    {
        // 基础判断
        if (self::getLock() > time() || !getEnable('judgement')) {
            return;
        }
        // 资格判断 没有资格就60-120分钟后计息 不排除其他错误
        if (!self::jury()) {
            self::setLock(mt_rand(60, 120) * 60);
        }
        // 任务
        if (empty(self::$wait_case)) {
            // 获取
            $case_id = self::caseObtain();
            self::caseCheck($case_id);
        } else {
            // 执行
            $case = array_pop(self::$wait_case);
            self::vote($case['id'], $case['vote']);
        }
        // 如果没有设置时间 就设置个默认时间 可能在一秒钟内处理完 所以 <=
        if (self::getLock() <= time()) {
            self::setLock(mt_rand(15, 30) * 60);
        }

    }


    /**
     * @use 案件核查
     * @param $case_id
     * @return bool|void
     */
    private static function caseCheck($case_id)
    {
        if ($case_id == '') {
            return true;
        }
        $case_info = self::caseInfo($case_id);
        $case_opinion = self::caseOpinion($case_id);
        if (!$case_opinion && empty($case_opinion)) {
//            $vote_info = $case_info[array_rand($case_info)];
            $vote_info = $case_info[self::probability()];

        } else {
            $vote_info = $case_opinion[array_rand($case_opinion)];
        }
        $vote = $vote_info['vote'];
        $vote_text = $vote_info['vote_text'];
        Log::info("案件 $case_id 的预测投票结果：$vote($vote_text)");
        self::$wait_case[] = ["id" => $case_id, 'vote' => $vote];
        // 尝试修复25018 未测试
        self::vote($case_id, 0);

        self::setLock(60 + 5);
    }

    /**
     * @use 投票
     * @param string $case_id
     * @param int $vote
     */
    private static function vote(string $case_id, int $vote): void
    {
        $url = 'https://api.bilibili.com/x/credit/v2/jury/vote';
        $payload = [
            "case_id" => $case_id, # 案件id
            "vote" => $vote, # 投票选项
            "content" => "", # 内容
            "anonymous" => 0,  # 是否匿名
            "insiders" => array_rand([0, 1]),  # 是否观看
            "csrf" => getCsrf(), # 验证
        ];
        $raw = Curl::post('pc', $url, $payload, self::$default_headers);
        $de_raw = json_decode($raw, true);
        // {"code":0,"message":"0","ttl":1}
        // {"code":25018,"message":"不能进行此操作","ttl":1}
        if (isset($de_raw['code']) && $de_raw['code']) {
            Log::warning("案件 $case_id 投票失败 $raw");
        } else {
            Log::notice("案件 $case_id 投票成功 $raw");
        }
    }

    /**
     * @use 申请连任资格
     */
    private static function juryApply(): void
    {
        $url = 'https://api.bilibili.com/x/credit/v2/jury/apply';
        $payload = [
            "csrf" => getCsrf(),
        ];
        $raw = Curl::post('pc', $url, $payload, self::$default_headers);
        $de_raw = json_decode($raw, true);
        // {"code":0,"message":"0","ttl":1}
        if (isset($de_raw['code']) && $de_raw['code']) {
            Log::warning("提交連任申請失敗 $raw");
        } else {
            Log::notice("提交連任申請成功 $raw");
            Notice::push('jury_auto_apply', '提交連任申請成功');
        }
    }


    /**
     * @use 获取众议观点
     */
    private static function caseOpinion(string $case_id, int $pn = 1, int $ps = 20)
    {
        $url = 'https://api.bilibili.com/x/credit/v2/jury/case/opinion';
        $payload = [
            'case_id' => $case_id,
            'pn' => $pn,
            'ps' => $ps
        ];

        $raw = Curl::get('pc', $url, $payload, self::$default_headers);
        $de_raw = json_decode($raw, true);
        // {"code":0,"message":"0","ttl":1,"data":{"total":438,"list":[]}}
        if (isset($de_raw['code']) && $de_raw['code']) {
            return false;
        } else {
            return $de_raw['data']['list'];
        }
    }


    /**
     * @use 获取案例详情
     * @param string $case_id
     * @return mixed
     */
    private static function caseInfo(string $case_id): mixed
    {
        $url = 'https://api.bilibili.com/x/credit/v2/jury/case/info';
        $payload = [
            'case_id' => $case_id,
        ];
        $raw = Curl::get('pc', $url, $payload, self::$default_headers);
        $de_raw = json_decode($raw, true);
        // {"code":0,"message":"0","ttl":1,"data":{"case_id":"","case_type":1,"vote_items":[{"vote":1,"vote_text":"合适"},{"vote":2,"vote_text":"一般"},{"vote":3,"vote_text":"不合适"},{"vote":4,"vote_text":"无法判断"}],"default_vote":4,"status":0,"origin_start":0,"avid":,"cid":,"vote_cd":5,"case_info":{"comment":{"uname":"用户1","face":"xxxx"},"danmu_img":""}}}
        if (isset($de_raw['code']) && $de_raw['code']) {
            return false;
        } else {
            return $de_raw['data']['vote_items'];
        }
    }

    /**
     * @use 获取案件任务
     * @return string
     */
    private static function caseObtain(): string
    {
        $url = 'https://api.bilibili.com/x/credit/v2/jury/case/next';
        $payload = [];
        $raw = Curl::get('pc', $url, $payload, self::$default_headers);
        $de_raw = json_decode($raw, true);
        // {"code":0,"message":"0","ttl":1,"data":{"case_id":"AC1xx411c7At"}}
        // {"code":25008,"message":"真给力 , 移交众裁的举报案件已经被处理完了","ttl":1}
        // {"code":25014,"message":"25014","ttl":1}
        // {"code":25005,"message":"请成为風機委員后再试","ttl":1}
        // {"code":25006,"message":"風機委員资格已过期","ttl":1}
        if (isset($de_raw['code']) && $de_raw['code']) {
            switch ($de_raw['code']) {
                case 25005:
                    Log::warning($de_raw['message']);
                    self::setLock(self::timing(10));
                    break;
                case 25006:
                    Log::warning($de_raw['message']);
                    Notice::push('jury_leave_office', $de_raw['message']);
                    self::setLock(self::timing(10));
                    break;
                case 25008:
                    Log::info("暂时没有新的案件需要审理~ $raw");
                    break;
                case 25014:
                    Log::info("今日案件已审满，感谢您对社区的贡献！明天再来看看吧~");
                    self::setLock(self::timing(7, 0, 0, true));
                    break;
                default:
                    Log::info("获取案件失败~ $raw");
            }
            return '';
        } else {
            $case_id = $de_raw['data']['case_id'];
            Log::info("获取到案例ID $case_id ~");
            return $case_id;
        }

    }

    /**
     * @use 陪审团
     * @return bool
     */
    private static function jury(): bool
    {
        $url = 'https://api.bilibili.com/x/credit/v2/jury/jury';
        $payload = [];
        $raw = Curl::get('pc', $url, $payload, self::$default_headers);
        $de_raw = json_decode($raw, true);
        // {"code":25005,"message":"请成为風機委員后再试","ttl":1}
        // {"code":0,"message":"0","ttl":1,"data":{"uname":"","face":"http://i2.hdslb.com/bfs/face/.jpg","case_total":,"term_end":,"status":1}}
        if (isset($de_raw['code']) && $de_raw['code']) {
            return false;
        }
//        "status": 1  理论正常
//        "status": 2,  没有资格
//        "apply_status": -1 已经卸任但未连任
//        "apply_status": 0 申请连任后
//        "apply_status": 5 审核连任中
//        "apply_status": 3 连任成功后
//        "apply_status": 4 申请连任失败(?)
        // 理论上正常
        if ($de_raw['data']['status'] == 1) {
            Log::info('你可以參與社區衆裁，共創良好環境哦~');
            return true;
        }
        // 只是嘗試
        if ($de_raw['data']['apply_status'] == -1 && getConf('auto_apply', 'judgement')) {
            self::juryApply();
        }

        return false;

    }

    /**
     * @use 获取案例数据|风纪检测
     * @return bool
     */
    private static function judgementIndex(): bool
    {
        $url = 'https://api.bilibili.com/x/credit/jury/caseList';
        $headers = [
            'Referer' => "https://www.bilibili.com/judgement/index"
        ];
        $payload = [
            'callback' => "jQuery1720" . self::randInt() . "_" . time(),
            'pn' => 1,
            'ps' => 25,
            '_' => time()
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
//        print_r($de_raw);
        Log::debug($raw);
        $data = $de_raw['data'];
        if (!$data) {
            Log::info('该用户非风纪委成员');
            return false;
        }
        $today = date("Y-m-d");
        $sum_cases = 0;
        $valid_cases = 0;
        $judging_cases = 0;
        foreach ($data as $case) {
            $ts = $case['voteTime'] / 1000;
            $vote_day = date("Y-m-d", $ts);
            if ($vote_day == $today) {
                $sum_cases += 1;
                $vote = $case['vote'];
                if ($vote) {
                    $valid_cases += 1;
                } else {
                    $judging_cases += 1;
                }
            }
        }
        Log::info("今日投票{$sum_cases}（{$valid_cases}票有效（非弃权），{$judging_cases}票还在进行中）");
        return true;
    }

    /**
     * @use 随机整数
     * @param int $max
     * @return string
     */
    private static function randInt(int $max = 17): string
    {
        $temp = [];
        foreach (range(1, $max) as $ignored) {
            $temp[] = mt_rand(0, 9);
        }
        return implode("", $temp);
    }

    /**
     * @use 概率
     * @return int
     */
    private static function probability(): int
    {
        $result = 0;
        $prize_arr = [0 => 25, 1 => 40, 2 => 25, 3 => 10];
        // 概率数组的总概率精度
        $sum = array_sum($prize_arr);
        // 概率数组循环
        foreach ($prize_arr as $key => $value) {
            if (mt_rand(1, $sum) <= $value) {
                $result = $key;
                break;
            }
            $sum -= $value;
        }
        return $result;
    }

}