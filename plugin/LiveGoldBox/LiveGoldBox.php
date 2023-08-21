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

use Bhp\Api\XLive\LotteryInterface\V2\ApiBox;
use Bhp\Cache\Cache;
use Bhp\FilterWords\FilterWords;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;
use Bhp\Util\Exceptions\NoLoginException;

class LiveGoldBox extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    protected ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'LiveGoldBox', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '直播金色宝箱(实物抽奖)', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1110, // 插件优先级
        'cycle' => '6-10(分钟)', // 运行周期
    ];

    /**
     * @var int
     */
    protected int $start_aid = 0;

    /**
     * @var int
     */
    protected int $stop_aid = 0;

    /**
     * @var array
     */
    protected array $invalid_aids = [];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        // 时间锁
        TimeLock::initTimeLock();
        // 缓存
        Cache::initCache();
        // $this::class
        $plugin->register($this, 'execute');
    }

    /**
     * 执行
     * @return void
     * @throws NoLoginException
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('live_gold_box')) return;
        // 2022-06-07
        $this->calcAidRange(1030, 1230);
        //
        $lottery_list = $this->fetchLotteryList();
        //
        $this->drawLottery($lottery_list);
        //
        TimeLock::setTimes(mt_rand(6, 10) * 60);
    }

    /**
     * 过滤轮次
     * @param array $rounds
     * @return int
     */
    protected function filterRound(array $rounds): int
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
     * 过滤抽奖Title
     * @param string $title
     * @return bool
     */
    protected function filterTitleWords(string $title): bool
    {
        $sensitive_words = FilterWords::getInstance()->get('LiveGoldBox.sensitive');
        //
        foreach ($sensitive_words as $word) {
            if (str_contains($title, $word)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 抽奖
     * @param array $lottery_list
     * @return void
     */
    protected function drawLottery(array $lottery_list): void
    {
        foreach ($lottery_list as $lottery) {
            extract($lottery);
            $response = ApiBox::draw($aid, $num);
            if ($response['code']) {
                Log::warning("金色宝箱: $title($aid->$num) 参与抽奖失败 {$response['code']} -> {$response['message']}~");
            } else {
                Log::notice("色宝箱: $title($aid->$num) 参与抽奖成功~");
            }
        }
    }

    /**
     * 获取抽奖
     * @return array
     * @throws NoLoginException
     */
    protected function fetchLotteryList(): array
    {
        // 缓存开始 如果存在就赋值 否则默认值
        $this->invalid_aids = ($tmp = Cache::get('invalid_aids')) ? $tmp : [];
        //
        $lottery_list = [];
        $max_probe = 10;
        $probes = range($this->start_aid, $this->stop_aid);
        foreach ($probes as $probe_aid) {
            // 最大试探
            if ($max_probe == 0) break;
            // 无效列表
            if (in_array($probe_aid, $this->invalid_aids)) {
                continue;
            }
            // 试探
            $response = $this->boxInfos($probe_aid);
            if (empty($response)) {
                $max_probe--;
                continue;
            }
            //
            $rounds = $response['typeB'];
            $last_round = end($rounds);
            // 最后抽奖轮次无效
            if ($last_round['join_end_time'] < time()) {
                $this->invalid_aids[] = $probe_aid;
                continue;
            }
            // 过滤敏感词
            $title = $response['title'];
            if ($this->filterTitleWords($title)) {
                $this->invalid_aids[] = $probe_aid;
                continue;
            }
            // 过滤抽奖轮次
            $round_num = $this->filterRound($rounds);
            if ($round_num == 0) {
                continue;
            }
            $lottery_list[] = [
                'title' => $title,
                'aid' => $probe_aid,
                'num' => $round_num,
            ];
        }
        // 缓存结束 需要的数据的放进缓存
        Cache::set('invalid_aids', $this->invalid_aids);
        //
        Log::info('金色宝箱: 获取到有效抽奖列表 ' . count($lottery_list));
        return $lottery_list;
    }

    /**
     * 抽奖盒子信息
     * @param int $aid
     * @return array
     * @throws NoLoginException
     */
    protected function boxInfos(int $aid): array
    {
        // {"code":0,"data":null,"message":"ok","msg":"ok"}
        // {"code":0,"data":{"title":"荣耀宝箱抽奖","rule":"a 抽奖时间按如下规则抽取一次，重复无效。\nb 获奖者需要再获奖名单公布后一周内反馈姓名、邮寄地址、联系方式，因获奖者逾期查看获奖名单、逾期提交个人资料或个人资料有误，将视为自动放弃获奖资格及由此产生的权利。","current_round":2,"typeB":[{"startTime":"2020-05-18 18:30:00","imgUrl":"https://i0.hdslb.com/bfs/live/f600b89f2c2550b600612feba90e39901a9f027c.jpg","join_start_time":1589796000,"join_end_time":1589797800,"status":4,"list":[{"jp_name":"荣耀路由3","jp_num":"1","jp_id":3181,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/f600b89f2c2550b600612feba90e39901a9f027c.jpg"}],"round_num":1},{"startTime":"2020-05-18 19:00:00","imgUrl":"https://i0.hdslb.com/bfs/live/f600b89f2c2550b600612feba90e39901a9f027c.jpg","join_start_time":1589797800,"join_end_time":1589799600,"status":0,"list":[{"jp_name":"荣耀路由3","jp_num":"1","jp_id":3182,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/f600b89f2c2550b600612feba90e39901a9f027c.jpg"}],"round_num":2},{"startTime":"2020-05-18 19:30:00","imgUrl":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg","join_start_time":1589799600,"join_end_time":1589801400,"status":-1,"list":[{"jp_name":"荣耀平板V6","jp_num":"1","jp_id":3183,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg"}],"round_num":3},{"startTime":"2020-05-18 20:00:00","imgUrl":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg","join_start_time":1589801400,"join_end_time":1589803200,"status":-1,"list":[{"jp_name":"荣耀平板V6","jp_num":"1","jp_id":3184,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg"}],"round_num":4},{"startTime":"2020-05-18 20:30:00","imgUrl":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg","join_start_time":1589803200,"join_end_time":1589805000,"status":-1,"list":[{"jp_name":"荣耀平板V6","jp_num":"1","jp_id":3185,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/9fcde6f26a546b9dfb5ffc7a0c4f4503a05e16f2.jpg"}],"round_num":5},{"startTime":"2020-05-18 21:00:00","imgUrl":"https://i0.hdslb.com/bfs/live/73db69bd5a9e5dedb7d2f32d72fd6248b860e238.jpg","join_start_time":1589805000,"join_end_time":1589806800,"status":-1,"list":[{"jp_name":"荣耀MagicBook Pro","jp_num":"1","jp_id":3186,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/73db69bd5a9e5dedb7d2f32d72fd6248b860e238.jpg"}],"round_num":6},{"startTime":"2020-05-18 22:00:00","imgUrl":"https://i0.hdslb.com/bfs/live/4dba1e8b58c174d5e2311de339b1e02a3ac77a98.jpg","join_start_time":1589806800,"join_end_time":1589810400,"status":-1,"list":[{"jp_name":"荣耀智慧屏新品，荣耀MagicBook Pro，荣耀平板V6，荣耀路由3","jp_num":"1","jp_id":3187,"jp_type":2,"ex_text":"","jp_pic":"https://i0.hdslb.com/bfs/live/4dba1e8b58c174d5e2311de339b1e02a3ac77a98.jpg"}],"round_num":7}],"activity_pic":"https://i0.hdslb.com/bfs/live/c3ed87683f6e87d256d1f5fdddbfb220fc4c2cdf.png","activity_id":556,"weight":20,"background":"https://i0.hdslb.com/bfs/live/84cd59bcb1e977359df618dbeb0f7828751f457c.png","title_color":"#FFFFFF","closeable":0,"jump_url":"https://live.bilibili.com/p/html/live-app-treasurebox/index.html?is_live_half_webview=1\u0026hybrid_biz=live-app-treasurebox\u0026hybrid_rotate_d=1\u0026hybrid_half_ui=1,3,100p,70p,0,0,30,100;2,2,375,100p,0,0,30,100;3,3,100p,70p,0,0,30,100;4,2,375,100p,0,0,30,100;5,3,100p,70p,0,0,30,100;6,3,100p,70p,0,0,30,100;7,3,100p,70p,0,0,30,100\u0026aid=556"},"message":"","msg":""}
        $response = ApiBox::getStatus($aid);
        //
        switch ($response['code']) {
            case -500:
                throw new NoLoginException($response['message']);
            case 0:
                //
                if (is_null($response['data'])) {
                    return [];
                }
                //
                return $response['data'];
            default:
                Log::warning("金色宝箱: 获取宝箱{$aid}状态失败 {$response['code']} -> {$response['message']}");
                return [];
        }
    }

    /**
     * 计算范围
     * @param int $min
     * @param int $max
     * @return void
     * @throws NoLoginException
     */
    protected function calcAidRange(int $min, int $max): void
    {
        if ($this->start_aid && $this->stop_aid) return;
        //
        while (true) {
            $middle = intval(($min + $max) / 2);
            $info = $this->boxInfos($middle);
            if (empty($info)) {
                $info = $this->boxInfos($middle + mt_rand(0, 3));
                if (empty($info)) {
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
        //
        $this->start_aid = $min - mt_rand(15, 30);
        $this->stop_aid = $min + mt_rand(15, 30);
        //
        Log::info("金色宝箱: 设置穷举范围($this->start_aid -> $this->stop_aid)");
    }

}
