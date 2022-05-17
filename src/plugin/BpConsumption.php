<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Curl;
use BiliHelper\Core\Log;
use BiliHelper\Tool\Common;
use BiliHelper\Util\TimeLock;

class BpConsumption
{

    use TimeLock;

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('bp_consumption')) {
            return;
        }
        // 定时14点 + 随机120分钟| 根据逻辑前置
        self::setLock(self::timing(14) + mt_rand(1, 120) * 60);

        // 如果为年度大会员
        if (User::isYearVip()) {
            // 获取B币余额
            $bp_balance = self::getUserWallet();
            // 最大支持5
            if ($bp_balance != 5) return;
            // 消费B币充电
            if (getConf('bp2charge', 'bp_consumption')) {
                // UID为空就切换成自己的
                $uid = empty($uid = getConf('bp2charge_uid', 'bp_consumption')) ? getUid() : $uid;
                self::BP2charge($uid, $bp_balance);
                return;
            }
            // 消费B币充值金瓜子
            if (getConf('bp2gold', 'bp_consumption')) {
                self::BP2gold($bp_balance);
            }
        }
    }

    /**
     * @use 获取钱包B币券余额
     * @return int
     */
    private static function getUserWallet(): int
    {
        $url = 'https://pay.bilibili.com/paywallet/wallet/getUserWallet';
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
            'origin' => 'https://pay.bilibili.com',
            'referer' => 'https://pay.bilibili.com/paywallet-fe/bb_balance.html'
        ];
        $ts = Common::getUnixTimestamp();
        $payload = [
            'panelType' => 3,
            'platformType' => 3,
            'timestamp' => $ts,
            'traceId' => $ts,
            'version' => "1.0",
        ];
        $raw = Curl::put('pc', $url, $payload, $headers);
        // {"errno":0,"msg":"SUCCESS","showMsg":"","errtag":0,"data":{"mid":1234,"totalBp":5.00,"defaultBp":0.00,"iosBp":0.00,"couponBalance":5.00,"availableBp":5.00,"unavailableBp":0.00,"unavailableReason":"苹果设备上充值的B币不能在其他平台的设备上进行使用","tip":null}}
        $de_raw = json_decode($raw, true);
        if ($de_raw['errno'] == 0 && isset($de_raw['data']['couponBalance'])) {
            Log::notice('获取钱包成功 B币券余额剩余' . $de_raw['data']['couponBalance']);
            return intval($de_raw['data']['couponBalance']);
        } else {
            Log::warning("获取钱包失败 $raw");
            return 0;
        }
    }

    /**
     * @use B币充电
     * @param int $uid
     * @param int $num
     */
    private static function BP2charge(int $uid, int $num = 5): void
    {
        $url = 'https://api.bilibili.com/x/ugcpay/web/v2/trade/elec/pay/quick';
        $payload = [
            'bp_num' => $num, // 数量
            'is_bp_remains_prior' => true, // 是否优先扣除B币余额
            'up_mid' => $uid, // 目标UID
            'otype' => 'up', // 来源 up：空间充电 archive：视频充电
            'oid' => $uid, // 目标UID or 稿件avid
            'csrf' => getCsrf()
        ];
        $raw = Curl::post('pc', $url, $payload);
        // {"code":0,"message":"0","ttl":1,"data":{"mid":12324,"up_mid":1234,"order_no":"PAY4567","bp_num":"5","exp":5,"status":4,"msg":""}}
        // {"code":0,"message":"0","ttl":1,"data":{"mid":12324,"up_mid":1234,"order_no":"ABCD","bp_num":2,"exp":2,"status":4,"msg":""}}
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0) {
            // data.status 4 成功 -2：低于20电池下限 -4：B币不足
            if ($de_raw['data']['status'] == 4) {
                Log::notice("给{$uid}B币充电成功 NUM -> {$de_raw['data']['bp_num']} EXP -> {$de_raw['data']['exp']} ORDER -> {$de_raw['data']['order_no']}");
            } else {
                Log::warning("给{$uid}B币充电失败 STATUS -> {$de_raw['data']['status']} MSG -> {$de_raw['data']['msg']}");
            }
        } else {
            Log::warning("给{$uid}B币充电失败 CODE -> {$de_raw['code']} MSG -> {$de_raw['message']} ");
        }
    }

    /**
     * B币充值金瓜子
     * @param int $num
     */
    private static function BP2gold(int $num): void
    {
        $url = 'https://api.live.bilibili.com/xlive/revenue/v1/order/createOrder';
        $headers = [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index'
        ];
        $payload = [
            'platform' => 'pc',
            'pay_bp' => $num * 1000, // 瓜子数量
            'context_id' => 1, // 直播间
            'context_type' => 11,
            'goods_id' => 1, // 商品ID
            'goods_num' => $num, // B币数量
            'csrf_token' => getCsrf(),
            'csrf' => getCsrf(),
            'visit_id' => '',
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        // {"code":1300014,"message":"b币余额不足","ttl":1,"data":null}
        // {"code":0,"message":"0","ttl":1,"data":{"status":2,"order_id":"1234171134577071132741234","gold":0,"bp":5000}}
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0) {
            Log::notice("B币充值金瓜子成功 NUM -> {$de_raw['data']['bp']} ORDER -> {$de_raw['data']['order_id']}");
        } else {
            Log::warning("B币充值金瓜子失败 CODE -> {$de_raw['code']} MSG -> {$de_raw['message']}");
        }
    }

}