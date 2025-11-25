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

use Bhp\Api\Api\X\Player\ApiPlayer;
use Bhp\Api\DynamicSvr\ApiDynamicSvr;
use Bhp\Api\Video\ApiCoin;
use Bhp\Api\Video\ApiShare;
use Bhp\Api\Video\ApiWatch;
use Bhp\Api\Video\ApiVideo;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;
use Bhp\User\User;
use Bhp\Util\ArrayR\ArrayR;
use Bhp\Util\Exceptions\NoLoginException;
use Bhp\Cache\Cache;

class MainSite extends BasePlugin
{
    /**
     * @var array|array[]
     */
    protected array $records = [];

    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
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
        //
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
        if (TimeLock::getTimes() > time() || !getEnable('main_site')) return;
        // 缓存开始
        $this->records = ($tmp = Cache::get('records')) ? $tmp : $this->initRecords();
        //
        if ($this->watchTask() && $this->shareTask() && $this->coinTask()) {
            TimeLock::setTimes(TimeLock::timing(10));
        } else {
            // 失败重试时间 1~3小时 尝试通过延迟调整-403
            TimeLock::setTimes(mt_rand(60, 180) * 60);
        }
        // 缓存结束
        Cache::set('records', $this->records);
    }

    /**
     * 初始化
     * @return array[]
     */
    protected function initRecords(): array
    {
        return [
            'watch' => [],
            'share' => [],
            'coin' => [],
        ];
    }


    /**
     * 获取稿件列表
     * @param int $num
     * @return array
     */
    protected function fetchCustomArchives(int $num = 30): array
    {
        if (getConf('main_site.fetch_aids_mode') == 'random') {
            // 随机热门稿件榜单
            return $this->getTopArchives($num);
        } else {
            // 固定获取关注UP稿件榜单, 不足会随机补全
            return $this->getFollowUpArchives($num);
        }
    }


    /**
     * 投币任务
     * @param string $key
     * @return bool
     * @throws NoLoginException
     */
    protected function coinTask(string $key = 'coin'): bool
    {
        if (!getConf('main_site.add_coin', false, 'bool')) return true;
        // 已满6级
        if (getConf('main_site.when_lv6_stop_coin', false, 'bool')) {
            $userInfo = User::userNavInfo();
            if ($userInfo->level_info->current_level >= 6) {
                Log::notice('主站任务: 已满6级, 停止投币');
                return true;
            }
        };
        //
        if (in_array($this->getKey(), $this->records[$key])) return true;
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
            // 插入
            $this->records[$key][] = $this->getKey();
            return true;
        }
        // 稿件列表
        $aids = $this->fetchCustomArchives($actual_num);
        // 从二维数组里取出aid
        $aids = array_column($aids, 'aid');
        //
        Log::info("主站任务: 预投币稿件 " . implode(" ", $aids));
        // 投币
        foreach ($aids as $aid) {
            $this->reward((string)$aid);
            //
            sleep(1);
        }
        // 插入
        $this->records[$key][] = $this->getKey();
        return true;
    }

    /**
     * 投币
     * @param string $aid
     * @return void
     * @throws NoLoginException
     */
    protected function reward(string $aid): void
    {
        $response = ApiCoin::appCoin($aid);
        //
        switch ($response['code']) {
            case -101:
                throw new NoLoginException($response['message']);
            case 0:
                Log::notice("主站任务: $aid 投币成功");
                break;
            default:
                // Log::info(json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                Log::warning("主站任务: $aid 投币失败 {$response['code']} -> {$response['message']}");
        }
    }

    /**
     * 首页推荐
     * @param int $num
     * @param int $ps
     * @return array
     */
    protected function getTopArchives(int $num, int $ps = 30): array
    {
        $response = ApiVideo::dynamicRegion($ps);
        //
        if ($response['code']) {
            Log::warning("主站任务: 获取首页推荐失败 {$response['code']} -> {$response['message']}");
            return $this->getTopFeedRCMDArchives($num);
        }
        return ArrayR::toSlice($response['data']['archives'], $num);
    }

    /**
     * 获取榜单稿件列表
     * @param int $num
     * @return array
     */
    protected function getTopFeedRCMDArchives(int $num): array
    {
        $new_archives = [];
        //
        $response = ApiVideo::topFeedRCMD();
        $archives = ArrayR::toSlice($response['data']['item'], $num);
        //
        foreach ($archives as $archive) {
            $archive['aid'] = $archive['id'];
            unset($archive['id']);
            $new_archives[] = $archive;
        }
        return $new_archives;
    }

    /**
     * 获取关注UP稿件列表
     * @param int $num
     * @return array
     */
    protected function getFollowUpArchives(int $num): array
    {
        $archives = [];
        //
        $response = ApiDynamicSvr::followUpDynamic();
        //
        if ($response['code']) {
            Log::warning("主站任务: 获取UP稿件失败 {$response['code']} -> {$response['message']}");
        } else {
            foreach ($response['data']['cards'] as $i => $card) {
                // if ($i >= $num) break;
                // JSON_ERROR_CTRL_CHAR
                $temp = preg_replace('/[\x00-\x1F]/', '', $card['card']);
                $archives[] = json_decode($temp, true);
            }
            $archives = ArrayR::toSlice($archives, $num, false);
        }
        // 此处补全缺失
        if (($t_num = count($archives)) < $num) {
            Log::warning("主站任务: 获取UP稿件数量不足，将自动补全随机稿件。");
            $archives = array_merge($archives, $this->getTopArchives($num - $t_num));
        }
        return $archives;
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
            Log::warning("主站任务: 获取已投硬币失败 {$response['code']} -> {$response['message']}");
            return 0;
        }
        //
        $logs = $response['data']['list'];
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
     * @param string $key
     * @return bool
     * @throws NoLoginException
     */
    protected function shareTask(string $key = 'share'): bool
    {
        if (!getConf('main_site.share', false, 'bool')) return true;
        //
        if (in_array($this->getKey(), $this->records[$key])) return true;
        //
        $archives = $this->fetchCustomArchives(10);
        $archive = array_pop($archives);
        $aid = (string)$archive['aid'];

        //
        $response = ApiShare::share($aid);
        switch ($response['code']) {
            case -101:
                throw new NoLoginException($response['message']);
            case 0:
                Log::notice("主站任务: $aid 分享成功");
                // 插入
                $this->records[$key][] = $this->getKey();
                return true;
            default:
                Log::warning("主站任务: $aid 分享失败 {$response['code']} -> {$response['message']}，稍后将重试");
                return false;
        }
    }

    /**
     * 观看任务
     * @param string $key
     * @return bool
     */
    protected function watchTask(string $key = 'watch'): bool
    {
        if (!getConf('main_site.watch', false, 'bool')) return true;
        //
        if (in_array($this->getKey(), $this->records[$key])) return true;
        //
        $archives = $this->fetchCustomArchives(10);
        $archive = array_pop($archives);
        //
        if (isset($archive['duration']) && is_int($archive['duration'])) {
            $info = $archive;
        } else {
            // 额外处理信息
            $info = $this->getArchiveInfo((string)$archive['aid']);
            if (empty($info)) return false;
        }
        //
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
        // 插入
        $this->records[$key][] = $this->getKey();
        return true;
    }

    /**
     * 获取稿件详情/暂时不额外处理错误
     * @param string $aid
     * @return array
     */
    protected function getArchiveInfo(string $aid): array
    {
        $response = ApiPlayer::pageList($aid);
        //
        if ($response['code'] == -404 || !isset($response['data'])) {
            Log::warning("主站任务: $aid 获取稿件信息失败 {$response['code']} -> {$response['message']}");
            return [];
        }
        $archive_info = $response['data'][0];
        $archive_info['aid'] = $aid;
        return $archive_info;
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


    /**
     * @return string
     */
    protected function getKey(): string
    {
        return substr(md5(md5(date("Y-m-d", time()))), 8, 8);
    }

}
