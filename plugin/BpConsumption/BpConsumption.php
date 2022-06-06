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

use Bhp\Api\Pay\ApiPay;
use Bhp\Api\Pay\ApiWallet;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;
use Bhp\User\User;

class BpConsumption extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    protected ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'BpConsumption', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '大会员B币券消费', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1108, // 插件优先级
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
        if (TimeLock::getTimes() > time() || !getEnable('bp_consumption')) return;
        //
        $this->consumptionTask();
        // 定时14点 + 随机120分钟| 根据逻辑前置
        TimeLock::setTimes(TimeLock::timing(14) + mt_rand(1, 120) * 60);
    }

    /**
     * @use 消费
     * @return void
     */
    protected function consumptionTask(): void
    {
        // 如果为年度大会员
        if (!User::isYearVip('消费B币券')) return;
        // 获取B币余额
        $bp_balance = $this->getUserWallet();
        // 最大支持5
        if ($bp_balance != 5) return;
        // 消费B币充电
        if (getConf('bp_consumption.bp2charge', false, 'bool')) {
            // UID为空就切换成自己的
            $user = User::parseCookie();
            $up_mid = getConf('bp_consumption.bp2charge_uid', intval($user['uid']), 'int');
            $this->BP2charge($up_mid, $bp_balance);
            return;
        }
        // 消费B币充值金瓜子
        if (getConf('bp_consumption.bp2gold', false, 'bool')) {
            $this->BP2gold($bp_balance);
        }

    }

    /**
     * B币充值金瓜子
     * @param int $num
     */
    protected function BP2gold(int $num): void
    {
        // {"code":1300014,"message":"b币余额不足","ttl":1,"data":null}
        // {"code":0,"message":"0","ttl":1,"data":{"status":2,"order_id":"1234171134577071132741234","gold":0,"bp":5000}}
        $response = ApiPay::gold($num);
        //
        if ($response['code']) {
            Log::warning("消费B币券: 充值金瓜子失败 {$response['code']} -> {$response['message']}");
        } else {
            Log::notice("消费B币券: 充值金瓜子成功 NUM -> {$response['data']['bp']} ORDER -> {$response['data']['order_id']}");
        }
    }

    /**
     * @use B币充电
     * @param int $uid
     * @param int $num
     */
    protected function BP2charge(int $uid, int $num = 5): void
    {
        // {"code":0,"message":"0","ttl":1,"data":{"mid":12324,"up_mid":1234,"order_no":"PAY4567","bp_num":"5","exp":5,"status":4,"msg":""}}
        // {"code":0,"message":"0","ttl":1,"data":{"mid":12324,"up_mid":1234,"order_no":"ABCD","bp_num":2,"exp":2,"status":4,"msg":""}}
        $response = ApiPay::battery($uid, $num);
        //
        if ($response['code']) {
            Log::warning("消费B币券: 给{$uid}充电失败 {$response['code']} -> {$response['message']}");
        } else {
            // data.status 4 成功 -2：低于20电池下限 -4：B币不足
            if ($response['data']['status'] == 4) {
                Log::notice("消费B币券: 给{$uid}充电成功 NUM -> {$response['data']['bp_num']} EXP -> {$response['data']['exp']} ORDER -> {$response['data']['order_no']}");
            } else {
                Log::warning("消费B币券: 给{$uid}充电失败 {$response['data']['status']} -> {$response['data']['msg']}");
            }
        }
    }

    /**
     * @use 获取钱包B币券余额
     * @return int
     */
    protected function getUserWallet(): int
    {
        // {"errno":0,"msg":"SUCCESS","showMsg":"","errtag":0,"data":{"mid":1234,"totalBp":5.00,"defaultBp":0.00,"iosBp":0.00,"couponBalance":5.00,"availableBp":5.00,"unavailableBp":0.00,"unavailableReason":"苹果设备上充值的B币不能在其他平台的设备上进行使用","tip":null}}
        $response = ApiWallet::getUserWallet();
        if ($response['errno']) {
            Log::warning("消费B币券: 获取用户钱包信息失败 {$response['errno']} -> {$response['msg']}");
            return 0;
        }
        //
        $balance = $response['data']['couponBalance'];
        Log::info("消费B币券: 获取用户钱包信息成功 B币券余额剩余 {$balance}");
        //
        return intval($balance);
    }

}
 