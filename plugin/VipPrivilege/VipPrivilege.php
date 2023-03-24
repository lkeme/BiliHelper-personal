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

use Bhp\Api\Vip\ApiPrivilege;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;
use Bhp\User\User;
use Bhp\Util\Exceptions\NoLoginException;

class VipPrivilege extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    protected ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'VipPrivilege', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '领取大会员权益', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1107, // 插件优先级
        'cycle' => '24(小时)', // 运行周期
    ];

    /**
     * @var array|string[]
     */
    protected array $privilege = [
        0 => '未知奖励',
        1 => '年度专享B币赠送',
        2 => '年度专享会员购优惠券',
        3 => '年度专享漫画礼包',
        4 => '大会员专享会员购包邮券',
        5 => '年度专享漫画礼包',
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
     * @throws NoLoginException
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('vip_privilege')) return;
        //
        $this->receiveTask();
        //
        // 定时11点 + 随机120分钟
        TimeLock::setTimes(TimeLock::timing(11) + mt_rand(1, 120) * 60);
    }

    /**
     * 领取
     * @return void
     * @throws NoLoginException
     */
    protected function receiveTask(): void
    {
        // 如果为年度大会员
        if (!User::isYearVip('大会员权益')) return;
        //
        $privilege_list = $this->myVipPrivilege();
        //
        foreach ($privilege_list as $privilege) {
            // 是否领取状态
            if ($privilege['state'] != 0) {
                continue;
            }
            // 领取奖励
            $this->myVipPrivilegeReceive($privilege['type']);
        }
    }

    /**
     * 获取我的大会员权益列表
     * @return array
     */
    protected function myVipPrivilege(): array
    {
        // {"code":0,"message":"0","ttl":1,"data":{"list":[{"type":1,"state":0,"expire_time":1622476799},{"type":2,"state":0,"expire_time":1622476799}]}}
        $response = ApiPrivilege::my();
        //
        if ($response['code']) {
            Log::warning("大会员权益: 获取权益列表失败 {$response['code']} -> {$response['message']}");
            return [];
        } else {
            Log::info('大会员权益: 获取权益列表成功 ' . count($response['data']['list']));
            return $response['data']['list'];
        }
    }

    /**
     * 领取我的大会员权益
     * @param int $type
     * @throws NoLoginException
     */
    protected function myVipPrivilegeReceive(int $type): void
    {
        // {"code":0,"message":"0","ttl":1}
        // {-101: "账号未登录", -111: "csrf 校验失败", -400: "请求错误", 69800: "网络繁忙 请稍后重试", 69801: "你已领取过该权益"}
        $response = ApiPrivilege::receive($type);
        //
        switch ($response['code']) {
            case -101:
                throw new NoLoginException($response['message']);
            case 0:
                Log::notice("大会员权益: 领取权益 {$this->privilege[$type]} 成功");
                break;
            default:
                Log::warning("大会员权益: 领取权益 {$this->privilege[$type]} 失败  {$response['code']} -> {$response['message']}");
                break;
        }
    }

}
