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

use Bhp\Api\Lottery\V1\ApiAward;
use Bhp\Api\XLive\GeneralInterface\V1\ApiGuardBenefit;
use Bhp\Api\XLive\LotteryInterface\V1\ApiAnchor;
use Bhp\Api\XLive\Revenue\V1\ApiWallet;
use Bhp\Cache\Cache;
use Bhp\Log\Log;
use Bhp\Notice\Notice;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;

class AwardRecords extends BasePlugin
{
    /**
     * @var array|array[]
     */
    protected array $records = [];

    /**
     * @var array|int[]
     */
    protected array $locks = [
        'operation' => 0,
        'award' => 0,
        'celestial' => 0,
        'bonus' => 0,
    ];

    /**
     * 插件信息
     * @var array|string[]
     */
    protected ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'AwardRecords', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '获奖记录', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1111, // 插件优先级
        'cycle' => '5(分钟)', // 运行周期
    ];

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
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('award_records')) return;
        //
        $this->awardRecordsTask();
        //
        TimeLock::setTimes(5 * 60);
    }

    /**
     * @return void
     */
    protected function awardRecordsTask(): void
    {
        // 缓存开始
        $this->records = ($tmp = Cache::get('records')) ? $tmp : $this->initRecords();
        //
        if ($this->locks['operation'] < time()) {
            $this->operation();
        }
        if ($this->locks['award'] < time()) {
            $this->award();
        }
        if ($this->locks['celestial'] < time()) {
            $this->celestial();
        }
        if ($this->locks['bonus'] < time()) {
            $this->bonus();
        }
        // 缓存结束
        Cache::set('records', $this->records);
    }

    /**
     * 运营奖惩|false#6|true#24
     * @param string $title
     * @return bool
     */
    protected function operation(string $title = '运营奖惩'): bool
    {
        $response = ApiWallet::apCenterList();
        //
        if ($response['code']) {
            Log::warning("获奖记录: 获取{$title}失败 {$response['code']} -> {$response['message']}");
            $this->locks['operation'] = time() + 6 * 60 * 60;
            return false;
        }
        //
        $now = date("m") . '月' . date("d") . '日';
        foreach ($response['data']['list'] as $data) {
            $info = $data['md'] . '-' . $data['desc'];
            //
            if (!in_array($info, $this->records['operation'])) {
                $this->records['operation'][] = $info;
            }
            // 需要通知
            if ($now != $data['md']) continue;
            Log::notice($info);
            Notice::push($title, $info);
        }
        $this->locks['operation'] = time() + 24 * 60 * 60;
        return true;
    }

    /**
     * 获奖记录|false#1|true#6
     * @param string $title
     * @return bool
     */
    protected function award(string $title = '获奖记录'): bool
    {
        $response = ApiAward::awardList();
        //
        if ($response['code']) {
            Log::warning("获奖记录: 获取{$title}失败 {$response['code']} -> {$response['message']}");
            $this->locks['award'] = time() + 60 * 60;
            return false;
        }
        //
        foreach ($response['data']['list'] as $data) {
            $info = $data['create_time'] . '-' . $data['id'] . '-' . $data['source'] . '-' . $data['gift_name'];
            //
            if (!in_array($info, $this->records['award'])) {
                $this->records['award'][] = $info;
            }
            //
            $create_time = strtotime($data['create_time']);  //礼物时间
            $day = ceil((time() - $create_time) / 86400);  //60s*60min*24h
            //
            // 范围
            if ($day <= 2 && $data['update_time'] == '') {
                Log::notice($info);
                Notice::push($title, $info);
            }
        }
        $this->locks['award'] = time() + 6 * 60 * 60;
        return true;
    }

    /**
     * 天选时刻|false#30m|true#10m
     * @param string $title
     * @return bool
     */
    protected function celestial(string $title = '天选时刻'): bool
    {
        $response = ApiAnchor::awardRecord();
        //
        if ($response['code']) {
            Log::warning("获奖记录: 获取{$title}失败 {$response['code']} -> {$response['message']}");
            $this->locks['celestial'] = time() + 30 * 60;
            return false;
        }
        //
        foreach ($response['data']['list'] as $data) {
            $info = $data['end_time'] . '-' . $data['id'] . '-' . $data['anchor_name'] . '-' . $data['award_name'];
            //
            if (!in_array($info, $this->records['celestial'])) {
                $this->records['celestial'][] = $info;
            }
            //
            $end_time = strtotime($data['end_time']);  //礼物时间
            $day = ceil((time() - $end_time) / 86400);  //60s*60min*24h
            // 范围
            if ($day <= 2) {
                Log::notice($info);
                Notice::push($title, $info);
            }
        }
        $this->locks['celestial'] = time() + 10 * 60;
        return true;
    }

    /**
     * 航海回馈|false#6|true#24
     * @param string $title
     * @return bool
     */
    protected function bonus(string $title = '航海回馈'): bool
    {
        $response = ApiGuardBenefit::winListByUser();
        //
        if ($response['code']) {
            Log::warning("获奖记录: 获取{$title}失败 {$response['code']} -> {$response['message']}");
            $this->locks['bonus'] = time() + 6 * 30 * 60;
            return false;
        }
        //
        foreach ($response['data']['list'] as $data) {
            $info = $data['settlement_time'] . '-' . $data['win_id'] . '-' . $data['anchor_name'] . '-' . $data['award_name'];
            //
            if (!in_array($info, $this->records['bonus'])) {
                $this->records['bonus'][] = $info;
            }
            //
            $settlement_time = strtotime($data['settlement_time']);  //礼物时间
            // 范围
            if (time() < $settlement_time) {
                Log::notice($info);
                Notice::push($title, $info);
            }
        }
        $this->locks['bonus'] = time() + 24 * 60 * 60;
        return true;
    }

    /**
     * 初始化
     * @return array[]
     */
    protected function initRecords(): array
    {
        return [
            'operation' => [],
            'award' => [],
            'celestial' => [],
            'bonus' => [],
        ];
    }
}
