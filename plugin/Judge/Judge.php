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

use Bhp\Api\Credit\ApiJury;
use Bhp\Log\Log;
use Bhp\Notice\Notice;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;

class Judge extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'Judge', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '風機委員', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1106, // 插件优先级
        'cycle' => '15-30(分钟)', // 运行周期
    ];

    /**
     * @var array
     */
    protected array $wait_case = [];

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

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        //
        TimeLock::initTimeLock();
//        // 需要缓存
//        Cache::initCache();
        // $this::class
        $plugin->register($this, 'execute');
    }

    /**
     * 执行
     * @return void
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('judge')) return;
        // 资格判断 没有资格就60-120分钟后计息 不排除其他错误
        if (!$this->juryInfo()) {
            TimeLock::setTimes(mt_rand(60, 120) * 60);
            return;
        }
        //
        $this->judgementTask();
        // 如果没有设置时间 就设置个默认时间 可能在一秒钟内处理完 所以 <=
        if (TimeLock::getTimes() <= time()) {
            TimeLock::setTimes(mt_rand(15, 30) * 60);
        }
    }

    /**
     * 審判任務
     * @return void
     */
    protected function judgementTask(): void
    {
        if (empty($this->wait_case)) {
            // 獲取
            $cid = $this->caseObtain();
            $this->caseCheck($cid);
        } else {
            // 執行
            $case = array_pop($this->wait_case);
            $this->vote($case['id'], $case['vote']);
        }
    }

    /**
     * 案件核查
     * @param string $case_id
     * @return bool
     */
    protected function caseCheck(string $case_id): bool
    {
        if ($case_id == '') return true;
        // 獲取詳情失敗
        $case_info = $this->caseInfo($case_id);
        if (empty($case_info)) return true;
        // 獲取衆議論失敗
        $case_opinion = $this->caseOpinion($case_id);
        if (empty($case_opinion)) {
            $vote_info = $case_info[$this->probability()];
        } else {
            $vote_info = $case_opinion[array_rand($case_opinion)];
        }
        //
        $vote = $vote_info['vote'];
        $vote_text = $vote_info['vote_text'];
        $this->wait_case[] = ['id' => $case_id, 'vote' => $vote];
        Log::info("風機委員: 案件{$case_id}的預測投票結果 $vote($vote_text)");
        // 尝试修复25018 未测试
        $this->vote($case_id, 0);
        //
        TimeLock::setTimes(60 + 5);
        return false;
    }

    /**
     * 投票
     * @param string $case_id
     * @param int $vote
     */
    private static function vote(string $case_id, int $vote): void
    {
        // {"code":0,"message":"0","ttl":1}
        // {"code":25018,"message":"不能进行此操作","ttl":1}
        $response = ApiJury::vote($case_id, $vote, '', 0, array_rand([0, 1]));

        if ($response['code']) {
            Log::warning("風機委員: 案件{$case_id}投票失败 {$response['code']} -> {$response['message']}");
        } else {
            Log::notice("風機委員: 案件{$case_id}投票成功");
        }
    }

    /**
     * 隨機整數
     * @param int $max
     * @return string
     */
    protected function randInt(int $max = 17): string
    {
        $temp = [];
        foreach (range(1, $max) as $ignored) {
            $temp[] = mt_rand(0, 9);
        }
        return implode("", $temp);
    }

    /**
     * 概率
     * @return int
     */
    protected function probability(): int
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

    /**
     * 获取衆議觀點
     * @param string $case_id
     * @return array
     */
    protected function caseOpinion(string $case_id): array
    {
        // {"code":0,"message":"0","ttl":1,"data":{"total":438,"list":[]}}
        $response = ApiJury::caseOpinion($case_id);
        //
        if ($response['code']) {
            Log::warning("風機委員: 獲取案件{$case_id}衆議觀點失敗 {$response['code']} -> {$response['message']}");
            return [];
        }
        // 防止为空 null
        if (is_null($response['data']['list']) || $response['data']['total'] == 0) {
            return [];
        }
        return $response['data']['list'];
    }

    /**
     * 获取案例详情
     * @param string $case_id
     * @return mixed
     */
    protected function caseInfo(string $case_id): array
    {
        // {"code":0,"message":"0","ttl":1,"data":{"case_id":"","case_type":1,"vote_items":[{"vote":1,"vote_text":"合适"},{"vote":2,"vote_text":"一般"},{"vote":3,"vote_text":"不合适"},{"vote":4,"vote_text":"无法判断"}],"default_vote":4,"status":0,"origin_start":0,"avid":,"cid":,"vote_cd":5,"case_info":{"comment":{"uname":"用户1","face":"xxxx"},"danmu_img":""}}}
        $response = ApiJury::caseInfo($case_id);
        //
        if ($response['code']) {
            Log::warning("風機委員: 獲取案件{$case_id}詳情失敗 {$response['code']} -> {$response['message']}");
            return [];
        }
        return $response['data']['vote_items'];
    }

    /**
     * 获取案件任务
     * @return string
     */
    protected function caseObtain(): string
    {
        // {"code":0,"message":"0","ttl":1,"data":{"case_id":"AC1xx411c7At"}}
        // {"code":25008,"message":"真给力 , 移交众裁的举报案件已经被处理完了","ttl":1}
        // {"code":25014,"message":"25014","ttl":1}
        // {"code":25005,"message":"请成为風機委員后再试","ttl":1}
        // {"code":25006,"message":"風機委員资格已过期","ttl":1}
        $response = ApiJury::caseNext();
        //
        switch ($response['code']) {
            case 0:
                $cid = $response['data']['case_id'];
                Log::info("風紀委員: 获取案例ID成功 $cid ~");
                return $cid;
            case 25005:
                Log::warning("風紀委員: 获取案例ID失敗 {$response['code']} -> {$response['message']}");
                TimeLock::setTimes(TimeLock::timing(10));
                break;
            case 25006:
                Log::warning("風紀委員: 获取案例ID失敗 {$response['code']} -> {$response['message']}");
                TimeLock::setTimes(TimeLock::timing(10));
                Notice::push('jury_leave_office', $response['message']);
                break;
            case 25008:
                Log::info("風紀委員: {$response['message']}");
                break;
            case 25014:
                Log::info("風紀委員: 今日案件已審滿，感謝您對社區的貢獻，明天再來看看吧~");
                TimeLock::setTimes(TimeLock::timing(7, 0, 0, true));
                break;
            default:
                Log::warning("風紀委員: 获取案例ID失敗 {$response['code']} -> {$response['message']}");
                break;
        }
        return '';
    }


    /**
     * 陪審團信息
     * @return bool
     */
    protected function juryInfo(): bool
    {
        // {"code":25005,"message":"请成为風機委員后再试","ttl":1}
        // {"code":0,"message":"0","ttl":1,"data":{"uname":"","face":"http://i2.hdslb.com/bfs/face/.jpg","case_total":,"term_end":,"status":1}}
        $response = ApiJury::jury();
        //
        if ($response['code']) return false;
//        "status": 1  理论正常
//        "status": 2,  没有资格
//        "apply_status": -1 已经卸任但未连任
//        "apply_status": 0 申请连任后
//        "apply_status": 5 审核连任中
//        "apply_status": 3 连任成功后
//        "apply_status": 4 申请连任失败(?)
        // 理论上正常
        if ($response['data']['status'] == 1) {
            Log::info('風機委員: 當前可以參與社區衆裁，共創良好環境哦~');
            return true;
        }
        $data = $response['data'];
        // 只是嘗試 TODO 更換位置
        if (getConf('judge.auto_apply', false, 'bool') && $data['allow_apply']) {
            // 情況壹
            if ($data['apply_status'] == -1 || $data['apply_status'] == 4) {
                $this->juryApply();
            }
            // 情況貳 一種特殊情況
            if ($data['apply_status'] == 3 && $data['status'] == 2 && $data['err_msg'] == base64_decode('5bey5Y245Lu76aOO57qq5aeU5ZGY')) {
                $this->juryApply();
            }
        }
        //
        Log::warning('風機委員: 當前沒有審判資格哦~');
        //
        return false;
    }

    /**
     * 申請連任資格
     * @return void
     */
    protected function juryApply(): void
    {
        // {"code":0,"message":"0","ttl":1}
        $response = ApiJury::juryApply();
        if ($response['code']) {
            Log::warning("風機委員: 申請連任提交失敗 {$response['code']} -> {$response['message']}");
        } else {
            Log::notice('風機委員: 申請連任提交成功');
            Notice::push('jury_auto_apply', '提交連任申請成功');
        }
    }

    /**
     * 获取案例數據
     * @return bool
     */
    private static function judgementIndex(): bool
    {
        $response = ApiJury::caseList();
        if ($response['code']) {
            Log::info("風紀委員: 獲取案例數據失敗 {$response['code']} -> {$response['message']}");
            return false;
        }
        //
        $data = $response['data'];
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
        Log::info("風紀委員: 今日投票{$sum_cases}（{$valid_cases}票有效（非棄權），{$judging_cases}票还在进行中）");
        return true;
    }
}
