<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

use Bhp\Api\DynamicSvr\ApiDynamicSvr;
use Bhp\Api\Video\ApiCoin;
use Bhp\Api\Video\ApiShare;
use Bhp\Api\Video\ApiWatch;
use Bhp\Api\Video\ApiVideo;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;

class MainSite extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    protected ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'MainSite', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '主站任务(观看|分享|投币)', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1100, // 插件优先级
        'cycle' => '24(小时)', // 运行周期
    ];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        //
        TimeLock::initTimeLock();
        // $this::class
        $plugin->register($this, 'execute');
    }

    /**
     * 执行
     * @return void
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('main_site')) return;
        //
        if ($this->watchTask() && $this->shareTask() && $this->coinTask()) {
            TimeLock::setTimes(TimeLock::timing(10));
        } else {
            TimeLock::setTimes(3600);
        }
    }

    /**
     * 投币任务
     * @return bool
     */
    protected function coinTask(): bool
    {
        if (!getConf('main_site.add_coin', false, 'bool')) return true;
        // 预计数量 失败默认0  避免损失
        $estimate_num = getConf('main_site.add_coin_num', 0, 'int');
        // 库存数量
        $stock_num = $this->getCoinStock();
        $already_num = $this->getCoinAlready();
        // 实际数量 处理硬币库存少于预计数量
        $actual_num = intval(min($estimate_num, $stock_num)) - $already_num;
        //
        Log::info("主站任务: 硬币库存 $stock_num 预投 $estimate_num 已投 $already_num 还需投币 $actual_num");
        // 上限
        if ($actual_num <= 0) {
            Log::notice('主站任务: 今日投币上限已满');
            return true;
        }
        // 稿件列表
        if (getConf('main_site.add_coin_mode') == 'random') {
            // 随机热门稿件榜单
            $aids = self::getTopAids($actual_num);
        } else {
            // 固定获取关注UP稿件榜单, 不足会随机补全
            $aids = self::getFollowUpAids($actual_num);
        }
        //
        Log::info("主站任务: 预投币稿件 " . implode(" ", $aids));
        // 投币
        foreach ($aids as $aid) {
            $this->reward((string)$aid);
        }
        return true;
    }

    /**
     * 投币
     * @param string $aid
     * @return void
     */
    protected function reward(string $aid): void
    {
        $response = ApiCoin::coin($aid);
        //
        if ($response['code']) {
            Log::warning("主站任务: $aid 投币失败 {$response['code']} -> {$response['message']}");
        } else {
            Log::notice("主站任务: $aid 投币成功");
        }
    }

    /**
     * 首页推荐
     * @param int $num
     * @param int $ps
     * @return array
     */
    protected function getTopAids(int $num, int $ps = 30): array
    {
        $aids = [];
        $response = ApiVideo::dynamicRegion($ps);
        //
        if ($response['code']) {
            Log::warning("主站任务: 获取首页推荐失败 {$response['code']} -> {$response['message']}");
            return self::getDayRankingAids($num);
        }
        //
        if ($num == 1) {
            $temps = [array_rand($response['data']['archives'], $num)];
        } else {
            $temps = array_rand($response['data']['archives'], $num);
        }
        foreach ($temps as $temp) {
            $aids[] = $response['data']['archives'][$temp]['aid'];
        }
        return $aids;
    }

    /**
     * 获取榜单稿件列表
     * @param int $num
     * @return array
     */
    protected function getDayRankingAids(int $num): array
    {
        $aids = [];
        $rand_nums = [];
        //
        $response = ApiVideo::ranking();
        //
        for ($i = 0; $i < $num; $i++) {
            while (true) {
                $rand_num = mt_rand(1, 99);
                if (in_array($rand_num, $rand_nums)) {
                    continue;
                } else {
                    $rand_nums[] = $rand_num;
                    break;
                }
            }
            $aid = $response['data']['list'][$rand_nums[$i]]['aid'];
            $aids[] = $aid;
        }
        //
        return $aids;
    }

    /**
     * 获取关注UP稿件列表
     * @param int $num
     * @return array
     */
    protected function getFollowUpAids(int $num): array
    {
        $aids = [];
        //
        $response = ApiDynamicSvr::followUpDynamic();
        //
        if ($response['code']) {
            Log::warning("主站任务: 获取UP稿件失败 {$response['code']} -> {$response['message']}");
            return $aids;
        }
        //
        foreach ($response['data']['cards'] as $index => $card) {
            if ($index >= $num) {
                break;
            }
            $aids[] = $card['desc']['rid'];
        }
        // 此处补全缺失
        if (count($aids) < $num) {
            $aids = array_merge($aids, $this->getTopAids($num - count($aids)));
        }
        return $aids;
    }

    /**
     * 已投币数量
     * @return int
     */
    protected function getCoinAlready(): int
    {
        $response = ApiCoin::addLog();
        //
        if ($response['code'] || !isset($response['data']['list'])) {
            Log::warning("主站任务: 获取已硬币失败 {$response['code']} -> {$response['message']}");
            return 0;
        }
        //
        $logs = $response['data']['list'] ?? [];
        $coins = 0;
        //
        foreach ($logs as $log) {
            $log_ux = strtotime($log['time']);
            $log_date = date('Y-m-d', $log_ux);
            $now_date = date('Y-m-d');
            if ($log_date != $now_date) {
                break;
            }
            if (str_contains($log['reason'], "打赏")) {
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
     * 获取硬币库存
     * @return int
     */
    protected function getCoinStock(): int
    {
        // {"code":0,"status":true,"data":{"money":1707.9}}
        // {"code":0,"status":true,"data":{"money":null}
        $response = ApiCoin::getCoin();
        //
        if ($response['code'] || !isset($response['data']['money'])) {
            Log::warning("主站任务: 获取硬币库存失败或者硬币为null " . json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return 0;
        }
        //
        return (int)$response['data']['money'];
    }

    /**
     * 分享任务
     * @return bool
     */
    protected function shareTask(): bool
    {
        if (!getConf('main_site.share', false, 'bool')) return true;
        // video*10
        $infos = $this->fetchRandomAvInfos();
        $info = array_pop($infos);
        $aid = (string)$info['aid'];
        //
        $response = ApiShare::share($aid);
        if ($response['code']) {
            Log::warning("主站任务: $aid 分享失败 {$response['code']} -> {$response['message']}");
            return false;
        }
        Log::notice("主站任务: $aid 分享成功");
        return true;
    }

    /**
     * 观看任务
     * @return bool
     */
    protected function watchTask(): bool
    {
        if (!getConf('main_site.watch', false, 'bool')) return true;
        // video*10
        $infos = $this->fetchRandomAvInfos();
        $info = array_pop($infos);
        $aid = (string)$info['aid'];
        $cid = (string)$info['cid'];
        $duration = (int)$info['duration'];
        //
        $response = ApiWatch::video($aid, $cid);
        // == 0
        if ($response['code']) {
            Log::warning("主站任务: $aid 观看失败 {$response['code']} -> {$response['message']}");
            return false;
        }
        $response = ApiWatch::heartbeat($aid, $cid, $duration);
        if ($response['code']) {
            Log::warning("主站任务: $aid 观看失败 {$response['code']} -> {$response['message']}");
            return false;
        }
        sleep(5);
        //
        $data = [];
        $data['played_time'] = $duration - 1;
        $data['play_type'] = 0;
        $data['start_ts'] = time();
        //
        $response = ApiWatch::heartbeat($aid, $cid, $duration, $data);
        if ($response['code']) {
            Log::warning("主站任务: $aid 观看失败 {$response['code']} -> {$response['message']}");
            return false;
        }
        //
        Log::notice("主站任务: $aid 观看成功");
        return true;
    }

    /**
     * 获取随机
     * @return array
     */
    protected function fetchRandomAvInfos(): array
    {
        do {
            $response = ApiVideo::newlist(mt_rand(1, 1000), 10);
        } while (count($response['data']['archives']) == 0);
        //
        $info = $response['data']['archives'];
        shuffle($info);
        //
        return $info;
    }

}
 