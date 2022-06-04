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

use Bhp\Api\LinkGroup\ApiLoveClub;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;

class LoveClub extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    protected ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'LoveClub', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '友爱社签到', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1102, // 插件优先级
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
     * @use 执行
     * @return void
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('love_club')) return;
        //
        $groups = $this->getGroupList();
        foreach ($groups as $group) {
            $this->signInGroup($group);
        }
        //
        TimeLock::setTimes(TimeLock::timing(10));
        // TimeLock::setTimes(mt_rand(8, 12) * 60 * 60);
    }

    /**
     * @use 获取友爱社列表
     * @return array
     */
    protected function getGroupList(): array
    {
        $response = ApiLoveClub::myGroups();
        //
        if ($response['code']) {
            Log::warning("友爱社: 获取应援团失败 {$response['code']} -> {$response['message']}");
            return [];
        }
        //
        if (empty($response['data']['list'])) {
            Log::notice('友爱社: 没有需要签到的应援团哦~');
            return [];
        }
        //
        return $response['data']['list'];
    }

    /**
     * @use 签到
     * @param array $groupInfo
     * @return bool
     */
    protected function signInGroup(array $group): bool
    {
        // {"code": 710001, "msg": "应援失败>_<", "message": "应援失败>_<", "ttl": "1", "data": {"add_num": 0, "status": 0}}
        $response = ApiLoveClub::signIn($group['group_id'], $group['owner_uid']);
        //
        if ($response['code']) {
            if ($response['code'] == '710001') {
                Log::notice("友爱社: {$group['group_name']} 签到失败, 亲密度已达上限了哦~");
            } else {
                Log::warning("友爱社: {$group['group_name']} 签到失败, {$response['code']} -> {$response['message']}");
            }
            return false;
        }
        //
        if ($response['data']['status']) {
            Log::notice("友爱社: {$group['group_name']} 签到失败, 今日已经签到过了哦~");
        } else {
            Log::notice("友爱社: {$group['group_name']} 签到成功, 亲密度+{$response['data']['add_num']}点");
        }
        return true;
    }

}
 