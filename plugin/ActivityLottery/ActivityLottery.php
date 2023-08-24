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

use Bhp\Cache\Cache;
use Bhp\Log\Log;
use Bhp\Notice\Notice;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\Request\Request;
use Bhp\TimeLock\TimeLock;
use function Amp\delay;

class ActivityLottery extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'ActivityLottery', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '转盘活动', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1117, // 插件优先级
        'cycle' => '3-7(分钟)', //  运行周期
        'start' => '07:30:00', // 插件运行开始时间
        'end' => '23:30:00', // 插件运行结束时间
    ];

    /**
     * @var array
     */
    protected array $config = [
        'invalid_sids' => [],
        'wait_add_infos' => [],
        'wait_get_infos' => [],
        'wait_do_infos' => [],
    ];

    /**
     * @var array
     */
    protected array $count0 = [];

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
        // 时间锁限制
        if (TimeLock::getTimes() > time() || !getEnable('activity_lottery')) return;
        // 今日执行
        if (isset($this->config[date("Y-m-d")]['add']) && isset($this->config[date("Y-m-d")]['get']) && isset($this->config[date("Y-m-d")]['do'])) return;
        //
        $this->initConfig();
        delay(1);
        // 获取远程数据
        $this->fetchRemoteInfos();
        delay(1);
        // 增加次数
        $this->addMyTimes();
        delay(1);
        // 查询次数
        $this->getMyTimes();
        delay(1);
        // 执行次数
        $this->doMyTimes();
        //
        $this->initConfig(true);
        //
        TimeLock::setTimes(mt_rand(3, 7) * 60);
    }


    /**
     * @param bool $ending
     * @return void
     */
    protected function initConfig(bool $ending = false): void
    {
        if ($ending) {
            Cache::set('config', $this->config);
        } else {
            // print_r(Cache::get('config'));
            $this->config = ($tmp = Cache::get('config')) ? $tmp : [];
            //
            $keys = ['invalid_sids', 'wait_add_infos', 'wait_get_infos', 'wait_do_infos'];
            foreach ($keys as $key) {
                if (!isset($this->config[$key])) $this->config[$key] = [];
            }
        }
    }

    /**
     * 执行次数
     * @return void
     */
    protected function doMyTimes(): void
    {
        if (isset($this->config[date("Y-m-d")]['do'])) return;
        //
        if (empty($this->config['wait_add_infos']) && empty($this->config['wait_get_infos']) && empty($this->config['wait_do_infos'])) {
            $this->config[date("Y-m-d")]['do'] = true;
            return;
        }
        //
        $info = array_shift($this->config['wait_do_infos']);
        if (is_null($info)) return;
        //
        Log::info("转盘活动: 当前活动 {$info['title']} 开始执行次数");
        //
        $response = Bhp\Api\Api\X\Activity\ApiActivity::doLottery($info);
        //
        $this->_doMyTimes($info, $response);
    }

    /**
     * 执行次数
     * @param array $info
     * @param array $data
     * @return void
     */
    protected function _doMyTimes(array $info, array $data): void
    {
        if ($this->checkInvalidSid($info, $data)) return;
        //
        if ($data['code'] != 0) {
            Log::warning("转盘活动: 当前活动 {$info['title']} 执行失败 Error: {$data['code']} -> {$data['message']}");
            return;
        }
        if (str_contains($data['data'][0]['gift_name'], '未中奖') || $data['data'][0]['gift_id'] == 0) {
            Log::notice("转盘活动: 当前活动 {$info['title']} 可能没有收获 {$data['data'][0]['gift_name']} ");
            return;
        }
        //
        Log::notice("转盘活动: 当前活动 {$info['title']} 执行成功 {$data['data'][0]['gift_name']}");
        Notice::push('activity_lottery', "转盘活动: 当前活动 {$info['title']} 执行成功 {$data['data'][0]['gift_name']}");
    }


    /**
     * 查询次数
     * @return void
     */
    protected function getMyTimes(): void
    {
        if (isset($this->config[date("Y-m-d")]['get'])) return;
        //
        if (empty($this->config['wait_add_infos']) && empty($this->config['wait_get_infos'])) {
            $this->config[date("Y-m-d")]['get'] = true;
            return;
        }
        //
        $info = array_shift($this->config['wait_get_infos']);
        if (is_null($info)) return;
        //
        Log::info("转盘活动: 当前活动 {$info['title']} 开始查询次数");
        //
        $response = Bhp\Api\Api\X\Activity\ApiActivity::myTimes($info);
        //
        $this->_getMyTimes($info, $response);
    }

    /**
     * 查询次数
     * @param array $info
     * @param array $data
     * @return void
     */
    protected function _getMyTimes(array $info, array $data): void
    {
        if ($this->checkInvalidSid($info, $data)) return;
        //
        if ($data['code'] != 0 || !isset($data['data']['times'])) {
            Log::warning("转盘活动: 当前活动 {$info['title']} 获取次数失败 Error: {$data['code']} -> {$data['message']}");
            return;
        }
        //
        if ($data['data']['times'] == 0) {
            // 连续两次没有次数，判定为已不可用。两天才能判定
            if (in_array($info['sid'], $this->count0)) {
                Log::warning("转盘活动: 当前活动 {$info['title']} 连续两次没有次数，判定为已不可用");
                $this->config['invalid_sids'][] = $info['sid'];
                return;
            } else {
                $this->count0[] = $info['sid'];
            }
            //
            Log::warning("转盘活动: 当前活动 {$info['title']} 没有次数了");
            return;
        }
        //
        Log::info("转盘活动: 当前活动 {$info['title']} 剩余次数 {$data['data']['times']}");
        for ($i = 0; $i < $data['data']['times']; $i++) {
            $this->config['wait_do_infos'][] = $info;
        }
    }

    /**
     * 增加次数
     * @return void
     */
    protected function addMyTimes(): void
    {
        if (isset($this->config[date("Y-m-d")]['add'])) return;
        //
        if (empty($this->config['wait_add_infos'])) {
            $this->config[date("Y-m-d")]['add'] = true;
            return;
        }
        //
        $info = array_shift($this->config['wait_add_infos']);
        if (is_null($info)) return;
        //
        Log::info("转盘活动: 当前活动 {$info['title']} 开始增加次数");
        //
        $response = Bhp\Api\Api\X\Activity\ApiActivity::addTimes($info);
        //
        $this->_addMyTimes($info, $response);

    }


    /**
     * 增加次数
     * @param array $info
     * @param array $data
     * @return void
     */
    protected function _addMyTimes(array $info, array $data): void
    {
        if ($this->checkInvalidSid($info, $data)) return;
        //
        if ($data['code'] != 0 || !isset($data['data']['add_num'])) {
            Log::warning("转盘活动: 当前活动 {$info['title']} 增加次数失败 Error: {$data['code']} -> {$data['message']}");
            return;
        }
        //
        Log::info("转盘活动: 当前活动 {$info['title']} 增加次数 {$data['data']['add_num']}");
        $this->config['wait_get_infos'][] = $info;
    }

    /**
     * @param array $info
     * @param array $data
     * @return bool
     */
    protected function checkInvalidSid(array $info, array $data): bool
    {
        if ($data['code'] == 170001 || $data['code'] == 175003 || $data['code'] == 170405) {
            Log::warning("转盘活动: 当前活动 {$info['title']} 已不可用 Error: {$data['code']} -> {$data['message']}");
            $this->config['invalid_sids'][] = $info['sid'];
            return true;
        }
        return false;
    }


    /**
     * 获取远程数据
     * @return void
     */
    protected function fetchRemoteInfos(): void
    {
        if (isset($this->config[date("Y-m-d")]['fetch'])) return;
        //
        $this->config['wait_add_infos'] = [];
        $this->config['wait_get_infos'] = [];
        $this->config['wait_do_infos'] = [];
        //
        // $url = 'aHR0cHM6Ly9yYXcua2dpdGh1Yi5jb20vbGtlbWUvQmlsaUhlbHBlci1wZXJzb25hbC9tYXN0ZXIvcmVzb3VyY2VzL2FjdGl2aXR5X2luZm9zLmpzb24=';
        $url = 'aHR0cHM6Ly9naHByb3h5LmNvbS9odHRwczovL3Jhdy5naXRodWJ1c2VyY29udGVudC5jb20vbGtlbWUvQmlsaUhlbHBlci1wZXJzb25hbC9tYXN0ZXIvcmVzb3VyY2VzL2FjdGl2aXR5X2luZm9zLmpzb24=';
        $url = base64_decode($url);
        $response = Request::getJson(true, 'other', $url);
        //
        $this->_fetchRemoteInfos($response['data']);
    }

    /**
     * 获取远程数据
     * @param array $data
     * @return void
     */
    protected function _fetchRemoteInfos(array $data): void
    {
        $new_data = [];
        //
        foreach ($data as $value) {
            // 活动无效
            if (in_array($value['sid'], $this->config['invalid_sids'])) continue;
            $new_data[] = $value;
        }
        // 获取乱序数据
        shuffle($new_data);
        $this->config['wait_add_infos'] = $new_data;
        //
        Log::info("转盘活动: 获取远程数据" . count($new_data) . "条");
        //
        $this->config[date("Y-m-d")]['fetch'] = true;
    }

}
