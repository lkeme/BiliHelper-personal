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

use Bhp\Api\Space\ApiArticle;
use Bhp\Cache\Cache;
use Bhp\FilterWords\FilterWords;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;
use Bhp\Request\Request;
use Bhp\User\User;
use Bhp\Util\Common\Common;
use function Amp\delay;


class Lottery extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'Lottery', // 插件名称
        'version' => '0.0.2', // 插件版本
        'desc' => '抽奖', // 插件描述
        'author' => 'MoeHero/Lkeme',// 作者
        'priority' => 1113, // 插件优先级
        'cycle' => '10-25(分钟)', // 运行周期
    ];

    /**
     * @var array|array[]
     */
    protected array $config = [
        'cv_list' => [], // 专栏列表
        'wait_cv_list' => [], // 待处理专栏列表
        'dynamic_list' => [], // 动态列表
        'wait_dynamic_list' => [], // 待处理动态列表
        'lottery_list' => [], // 抽奖列表
        'wait_lottery_list' => [], // 待处理抽奖列表
    ];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        TimeLock::initTimeLock();
        Cache::initCache();
        $plugin->register($this, 'execute');
    }

    /**
     * 执行
     * @return void
     */
    public function execute(): void
    {
        if (!getEnable('lottery')) return;
        //
        if (TimeLock::getTimes() > time()) return;
        //
        $this->initConfig();
        //
        $global_uid = base64_decode('MTkwNTcwMjM3NQ==');
        //
        $this->handleArticle($global_uid);
        //
        $this->handleDynamic($global_uid);
        //
        $this->handleLottery($global_uid);
        //
        $this->saveConfig();
        // 10-25分钟
        TimeLock::setTimes(mt_rand(10, 25) * 60);
    }

    /**
     * @return void
     */
    protected function initConfig(): void
    {
        $config = Cache::get('config');
        if ($config) {
            $this->config = $config;
        }
//        else {
//            $this->config['cv_list'] = [];
//            $this->config['wait_cv_list'] = [];
//            $this->config['dynamic_list'] = [];
//            $this->config['wait_dynamic_list'] = [];
//            $this->config['lottery_list'] = [];
//            $this->config['wait_lottery_list'] = [];
//        }
    }

    /**
     * @return void
     */
    protected function saveConfig(): void
    {
        Cache::set('config', $this->config);
    }


    /**
     * 处理专栏
     * @param string $uid
     * @return void
     */
    protected function handleArticle(string $uid): void
    {
        $this->fetchValidArticleUrls($uid);
        //
        $this->fetchValidDynamicUrl($uid);
        //
        delay(3);
    }

    /**
     * 处理动态
     * @param string $uid
     * @return void
     */
    protected function handleDynamic(string $uid): void
    {
        $this->fetchDynamicReserve();
        //
        delay(3);
    }

    /**
     * @param string $uid
     * @return void
     */
    protected function handleLottery(string $uid): void
    {
        $this->joinLottery();
    }

    /**
     * @return void
     */
    protected function joinLottery(): void
    {
        $lottery = array_shift($this->config['wait_lottery_list']);
        if (is_null($lottery)) return;
        //
        Log::info("抽奖: 尝试预约 ID: {$lottery['rid']} UP: {$lottery['up_mid']} 预约人数: {$lottery['reserve_total']}");
        Log::info("抽奖: 标题: {$lottery['title']}");
        Log::info("抽奖: 地址: " . $this->setT(intval($lottery['id_str'])));
        Log::info("抽奖: 奖品: {$lottery['prize']}");
        //
        if ($this->filterContentWords($lottery['title']) || $this->filterContentWords($lottery['prize'])) {
            Log::warning("抽奖: 预约失败，标题或描述含有敏感词, 跳过");
            return;
        }
        //
        $this->reserve($lottery);
    }


    /**
     * 参加抽奖
     * @param array $info
     * @return void
     */
    protected function reserve(array $info): void
    {
        //
        $user = User::parseCookie();
        //
        $url = 'https://api.vc.bilibili.com/dynamic_mix/v1/dynamic_mix/reserve_attach_card_button';
//        $headers = [
//            'origin' => 'https://space.bilibili.com',
//            'referer' => "https://space.bilibili.com/{$info['sender_uid']}/dynamic"
//        ];
        $headers = [
            'origin' => 'https://t.bilibili.com',
            'referer' => $this->setT(intval($info['id_str']))
        ];
        $payload = [
            'cur_btn_status' => 1,
            'dynamic_id' => $info['id_str'],
            'attach_card_type' => 'reserve',
            'reserve_id' => $info['rid'],
            'reserve_total' => $info['reserve_total'],
            'spmid' => '',
            'csrf' => $user['csrf'],
        ];
        //
        $response = Request::postJson(true, 'pc', $url, $payload, $headers);
        // $response['code'] === 7604003
        if ($response['code'] === 0) { //预约成功/已经预约
            Log::notice("抽奖: 预约成功 ReserveId: {$info['rid']}  Toast: {$response['data']['toast']} 已有{$response['data']['desc_update']} ");
        } else {
            Log::warning("抽奖: 预约失败 ReserveId: {$info['rid']}  Error: {$response['code']} -> {$response['message']}");
        }
    }


    /**
     * 获取有效动态列表
     * @param string $uid
     * @return void
     */
    protected function fetchValidDynamicUrl(string $uid): void
    {
        $cv = array_shift($this->config['wait_cv_list']);
        if (is_null($cv)) return;
        //
        $url = $this->setCv($cv);

        Log::info("抽奖: 开始提取专栏 $url");
        $payload = [];
        $headers = [
            'referer' => "https://space.bilibili.com/$uid/",
        ];
        $response = Request::get('pc', $url, $payload, $headers);
        //
        $this->_fetchValidDynamicUrl($response);
        //
        Log::info("抽奖: 获取有效动态列表成功 当前未处理Count: " . count($this->config['wait_dynamic_list']));
    }

    /**
     * 获取有效动态列表
     * @param string $data
     * @return array
     */
    protected function _fetchValidDynamicUrl(string $data): array
    {
        $urls = [];
        // 使用正则表达式从页面内容中提取URL
        $pattern = '/https:\/\/t\.bilibili\.com\/[0-9]+/';
        preg_match_all($pattern, $data, $matches);
        //
        foreach ($matches[0] as $url) {
            //
            if (in_array($this->getT($url), $this->config['dynamic_list'])) {
                continue;
            }
            //
            $this->addDynamicList($this->getT($url));
            //
            $urls[] = $url;

        }
        return $urls;
    }

    /**
     * 获取有效专栏列表
     * @param string $uid
     * @return void
     */
    protected function fetchValidArticleUrls(string $uid): void
    {
        //
        $response = ApiArticle::article($uid);
        //
        if ($response['code'] == 0) {
            $this->_fetchValidArticleUrls($response['data']);
            Log::info("抽奖: 获取有效专栏列表成功 当前未处理Count: " . count($this->config['wait_cv_list']));
        } else {
            Log::warning("抽奖: 获取有效专栏列表失败 Error: {$response['code']} -> {$response['message']}");
        }
        //
    }

    /**
     * 获取有效专栏列表
     * @param array $data
     * @return void
     */
    protected function _fetchValidArticleUrls(array $data): void
    {
        foreach ($data['articles'] as $item) {
            if (!Common::isTimestampInToday($item['publish_time'])) {
                continue;
            }
            if (!str_contains($item['title'], '抽奖') && !str_contains($item['title'], '预约')) {
                continue;
            }
            //
            if (in_array($item['id'], $this->config['cv_list'])) {
                continue;
            }
            $this->addCvList($item['id']);
            //
        }
    }

    /**
     * 获取动态预约
     * @return void
     */
    protected function fetchDynamicReserve(): void
    {
//        $t = array_shift($this->config['wait_dynamic_list']);
        $t = array_pop($this->config['wait_dynamic_list']);
        if (is_null($t)) return;
        $t_url = $this->setT($t);
        Log::info("抽奖: 开始提取动态 $t_url");
        //
        $url = 'https://api.bilibili.com/x/polymer/web-dynamic/v1/detail';
        $headers = [
            'origin' => 'https://t.bilibili.com',
            'referer' => $t_url,
        ];
        $payload = [
            'timezone_offset' => '-480',
            'id' => $t,
            'features' => 'itemOpusStyle'
        ];
        //
        $response = Request::getJson(true, 'pc', $url, $payload, $headers);
        //
        if ($response['code']) {
            Log::warning("抽奖: 提取动态($t)失败: {$response['code']} -> {$response['message']}");
            return;
        }
        //
        $this->_fetchDynamicReserve($response['data']);

        Log::info("抽奖: 获取有效预约列表成功 当前未处理Count: " . count($this->config['wait_lottery_list']));
    }

    /**
     * 获取动态预约
     * @param array $data
     * @return void
     */
    protected function _fetchDynamicReserve(array $data): void
    {
        if (!isset($data['item']['modules']['module_dynamic']['additional']['reserve'])) {
            Log::warning("抽奖: 提取动态预约失败: 未找到预约信息");
            return;
        }
        //
        if (!$data['item']['visible']) {
            Log::warning("抽奖: 提取动态预约失败: 动态已不可见");
            return;
        }
        //
        $reserve = $data['item']['modules']['module_dynamic']['additional']['reserve'];
        //
        if ($reserve['button']['uncheck']['text'] != '预约' || $reserve['button']['status'] != 1 || $reserve['button']['type'] != 2) {
            Log::warning("抽奖: 提取动态预约失败: 预约按钮状态异常");
            return;
        }
        //
        if ($reserve['state'] != 0 || $reserve['stype'] != 2) {
            Log::warning("抽奖: 提取动态预约失败: 预约动态状态异常");
            return;
        }
        //
        $lottery = [
            'reserve_total' => $reserve['reserve_total'],
            'rid' => $reserve['rid'],
            'title' => $reserve['title'],
            'up_mid' => $reserve['up_mid'],
            'prize' => $reserve['desc3']['text'],
            'id_str' => $data['item']['id_str'],
        ];
        $this->addLotteryList($lottery);
    }

    /**
     * @param array $lottery
     * @return void
     */
    protected function addLotteryList(array $lottery): void
    {
        if (!array_key_exists("rid{$lottery['rid']}", $this->config['lottery_list'])) {
            $this->config['lottery_list']["rid{$lottery['rid']}"] = $lottery;
            $this->config['wait_lottery_list']["rid{$lottery['rid']}"] = $lottery;
        }
    }

    /**
     * @param int $cv
     * @return void
     */
    protected function addCvList(int $cv): void
    {
        if (!in_array($cv, $this->config['cv_list'])) {
            $this->config['cv_list'][] = $cv;
            $this->config['wait_cv_list'][] = $cv;
        }
    }

    /**
     * @param int $dynamic
     * @return void
     */
    protected function addDynamicList(int $dynamic): void
    {
        if (!in_array($dynamic, $this->config['dynamic_list'])) {
            $this->config['dynamic_list'][] = $dynamic;
            $this->config['wait_dynamic_list'][] = $dynamic;
        }
    }

    /**
     * 获取cv号
     * @param string $url
     * @return int
     */
    protected function getCv(string $url): int
    {
        return intval(str_replace('https://www.bilibili.com/read/cv', '', $url));
    }

    /**
     * 设置cv号
     * @param int $cv
     * @return string
     */
    protected function setCv(int $cv): string
    {
        return 'https://www.bilibili.com/read/cv' . $cv;
    }

    /**
     * 获取动态
     * @param string $url
     * @return int
     */
    protected function getT(string $url): int
    {
        return intval(str_replace('https://t.bilibili.com/', '', $url));
    }

    /**
     * 设置动态
     * @param int $t
     * @return string
     */
    protected function setT(int $t): string
    {
        return 'https://t.bilibili.com/' . $t;
    }

    /**
     * 过滤抽奖信息
     * @param string $content
     * @return bool
     */
    protected function filterContentWords(string $content): bool
    {
        $sensitive_words = FilterWords::getInstance()->get('LiveGoldBox.sensitive');
        //
        foreach ($sensitive_words as $word) {
            if (str_contains($content, $word)) {
                return true;
            }
        }
        return false;
    }

//    /**
//     * 获取抽奖信息
//     * @param int $business_id
//     * @return array
//     */
//    protected function getLotteryInfo(int $business_id): array
//    {
//        delay(3);
//        $user = User::parseCookie();
//        $url = 'https://api.vc.bilibili.com/lottery_svr/v1/lottery_svr/lottery_notice';
//        $headers = [
//            'origin' => 'https://www.bilibili.com',
//            'referer' => 'https://www.bilibili.com/'
//        ];
//        $payload = [
//            'business_id' => $business_id,
//            'business_type' => 10, // 1.转发动态 10.直播预约
//            'csrf' => $user['csrf'],
//        ];
//        echo $business_id . PHP_EOL;
//        $response = Request::getJson(true, 'pc', $url, $payload, $headers);
//        print_r($response);
//
//        // 抽奖不存在/请求错误/请求被拦截
//        if ($response['code'] === -9999 || $response['code'] === 4000014 || $response['code'] === -412) {
//            return [
//                'status' => -9999,
//            ];
//        }
//        $data = $response['data'];
//        // 已开奖
//        if ($data['lottery_time'] <= time()) {
//            return [
//                'status' => 2,
//            ];
//        }
//
//        return [
//            'business_id' => $business_id, // business_type=1时是动态Id business_type=10时是预约直播Id
//            'lottery_id' => $data['lottery_id'], // 抽奖ID
//            'lottery_time' => $data['lottery_time'], // 开奖时间
//            'lottery_detail_url' => $data['lottery_detail_url'], // 抽奖详情页
//            'need_feed' => $data['lottery_feed_limit'] === 1, // 是否需要关注
//            'need_post' => $data['need_post'] === 1, //是否需要发货地址
//            'sender_uid' => $data['sender_uid'], // 发起人UID
//            'status' => $data['status'], // 0 未开奖 2 已开奖 -1 已失效 -9999 不存在
//            'type' => $data['business_type'], // 1.转发动态 10.直播预约
//            'ts' => $data['ts'], // 时间戳
//            'prizes' => $this->parsePrizes($data), // 奖品
//        ];
//    }
//
//
//    /**
//     * 解析奖品
//     * @param array $data
//     * @return string
//     */
//    protected function parsePrizes(array $data): string
//    {
//        $prizes = '';
//        $prizeData = [
//            'first_prize' => ['一等奖', 'first_prize_cmt'],
//            'second_prize' => ['二等奖', 'second_prize_cmt'],
//            'third_prize' => ['三等奖', 'third_prize_cmt']
//        ];
//        //
//        foreach ($prizeData as $key => $value) {
//            if (isset($data[$key]) && isset($data[$value[1]])) {
//                $prizes .= "{$value[0]}: {$data[$value[1]]} * {$data[$key]}\n";
//            }
//        }
//        //
//        return $prizes;
//    }
//
//    /**
//     * 提取动态参数
//     * @param string $data
//     * @return array
//     */
//    protected function extractDynamicParameters(string $data): array
//    {
//        $result = [];
//        preg_match('/"reserve_total":(\d+)/', $data, $b); // 正则匹配
//        if (isset($b[1])) {
//            $result['reserve_total'] = $b[1];
//        }
//        preg_match('/"reserve_total": (\d+)/', $data, $b); // 正则匹配
//        if (isset($b[1])) {
//            $result['reserve_total'] = $b[1];
//        }
//        preg_match('/"id_str":(\d+)/', $data, $b); // 正则匹配
//        if (isset($b[1])) {
//            $result['id_str'] = $b[1];
//        }
//        preg_match('/"id_str": (\d+)/', $data, $b); // 正则匹配
//        if (isset($b[1])) {
//            $result['id_str'] = $b[1];
//        }
//        return $result;
//    }
//
//    /**
//     * 过滤指定动态
//     * @param array $dynamic_list
//     * @param int $rid
//     * @return array
//     */
//    protected function filterDynamic(array $dynamic_list, int $rid): array
//    {
//        foreach ($dynamic_list as $dynamic) {
//            $dynamic_str = json_encode($dynamic);
//            if (str_contains($dynamic_str, '"rid":' . $rid) || str_contains($dynamic_str, '"rid": ' . $rid)) {
//                return $this->extractDynamicParameters($dynamic_str);
//            }
//        }
//        return [];
//    }
//
//    /**
//     * 获取指定空间动态列表
//     * @param int $host_mid
//     * @return array
//     */
//    protected function fetchSpaceDynamic(int $host_mid): array
//    {
//        $url = 'https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/space';
//        $headers = [
//            'origin' => 'https://space.bilibili.com',
//            'referer' => "https://space.bilibili.com/{$host_mid}/dynamic"
//        ];
//        $payload = [
//            'host_mid' => $host_mid,
//            'timezone_offset' => '-480',
//            'features' => 'itemOpusStyle'
//        ];
//        //
//        $response = Request::getJson(true, 'pc', $url, $payload, $headers);
//        //
//        if ($response['code']) {
//            Log::warning("获取({$host_mid})空间动态失败: {$response['code']} -> {$response['message']}");
//            return [];
//        }
//        //
//        return $response['data']['items'] ?? [];
//    }
//
//    protected function joinLottery(array $info): void
//    {
//        $dynamic_list = $this->fetchSpaceDynamic($info['sender_uid']);
//        $dynamic = $this->filterDynamic($dynamic_list, $info['business_id']);
//        if (!isset($dynamic['id_str']) || !isset($dynamic['reserve_total'])) {
//            Log::warning("抽奖: 未找到指定动态 ReserveId: $info[business_id]");
//            return;
//        }
//        // 合并数组
//        $info = array_merge($info, $dynamic);
//        $this->reserve($info);
//    }

}
