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

use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;

class BatchUnfollow extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'BatchUnfollow', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '批量取消关注', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1116, // 插件优先级
        'cycle' => '5-10(分钟)', // 运行周期
    ];

    /**
     * @var array
     */
    protected array $wait_unfollows = [];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        // 时间锁
        TimeLock::initTimeLock();
        // 缓存
        // Cache::initCache();
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
        if (TimeLock::getTimes() > time() || !getEnable('batch_unfollow')) return;
        //
        if (empty($this->wait_unfollows)) {
            $this->fetchFollows();
            //
            if (empty($this->wait_unfollows)) {
                TimeLock::setTimes(mt_rand(60, 120) * 60);
                return;
            }
        } else {
            $this->unfollow();
        }
        TimeLock::setTimes(mt_rand(5, 10) * 60);
    }

    /**
     * 获取关注列表
     * @return void
     */
    protected function fetchFollows(): void
    {
        $follows = [];
        //
        if (getConf('batch_unfollow.tag') == 'all') {
            $response = ApiRelation::followings(1, 50);
            if ($response['code'] != 0) {
                Log::warning("批量取关: 获取关注列表失败: {$response['code']} -> {$response['message']}");
                return;
            }
            foreach ($response['data']['list'] as $item) {
                $follows[] = ['mid' => $item['mid'], 'uname' => $item['uname']];
            }
        } else {
            $target_tag = getConf('batch_unfollow.tag');
            //
            $response = ApiRelation::tags();
            if ($response['code'] != 0) {
                Log::warning("批量取关: 获取分组列表失败: {$response['code']} -> {$response['message']}");
                return;
            }
            //
            $tagid = 0;
            foreach ($response['data'] as $item) {
                if ($item['name'] == $target_tag) {
                    $tagid = $item['tagid'];
                    break;
                }
            }
            if ($tagid == 0) {
                Log::warning("批量取关: 未找到目标分组: {$target_tag}");
                return;
            }
            //
            $response = ApiRelation::tag($tagid, 1, 50);
            if ($response['code'] != 0) {
                Log::warning("批量取关: 获取分组内列表失败: {$response['code']} -> {$response['message']}");
                return;
            }
            //
            foreach ($response['data']['list'] as $item) {
                $follows[] = ['mid' => $item['mid'], 'uname' => $item['uname']];
            }
        }

        $this->wait_unfollows = $follows;
        Log::info("批量取关: 获取关注列表成功 Count: " . count($follows));
    }

    /**
     * 取关
     * @return void
     */
    protected function unfollow(): void
    {
        $follow = array_shift($this->wait_unfollows);
        if (is_null($follow)) {
            Log::info("批量取关: 暂无关注列表");
            return;
        }
        //
        Log::info("批量取关: 尝试取关用户: {$follow['uname']}({$follow['mid']})");
        //
        $response = ApiRelation::modify($follow['mid']);
        if ($response['code'] != 0) {
            Log::warning("批量取关: 取关失败: {$response['code']} -> {$response['message']}");
            return;
        }
        Log::notice('批量取关: 取关成功');
    }


}
