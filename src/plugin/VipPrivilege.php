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
use BiliHelper\Util\TimeLock;

class VipPrivilege
{
    use TimeLock;

    private static array $privilege = [
        0 => '未知奖励',
        1 => 'B币劵',
        2 => '会员购优惠券'
    ];

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('vip_privilege')) {
            return;
        }
        // 如果为年度大会员
        if (User::isYearVip()) {
            $privilege_list = self::myVipPrivilege();
            foreach ($privilege_list as $privilege) {
                // 是否领取状态
                if ($privilege['state'] != 0) {
                    continue;
                }
                // 领取奖励
                self::myVipPrivilegeReceive($privilege['type']);
            }
        }
        // 定时11点 + 随机120分钟
        self::setLock(self::timing(11) + mt_rand(1, 120) * 60);
    }

    /**
     * @use 领取我的大会员权益
     * @param int $type
     */
    private static function myVipPrivilegeReceive(int $type): void
    {
        $url = 'https://api.bilibili.com/x/vip/privilege/receive';
        $headers = [
            'origin' => 'https://account.bilibili.com',
            'referer' => 'https://account.bilibili.com/account/big/myPackage',
        ];
        $payload = [
            'type' => $type,
            'csrf' => getCsrf(),
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        // {"code":0,"message":"0","ttl":1}
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0) {
            Log::notice('大会员权益 ' . self::$privilege[$type] . ' 领取成功');
        } else {
            Log::warning('大会员权益 ' . self::$privilege[$type] . " 领取失败, $raw");
        }
    }

    /**
     * @use 获取我的大会员权益列表
     * @return array
     */
    private static function myVipPrivilege(): array
    {
        $url = 'https://api.bilibili.com/x/vip/privilege/my';
        $headers = [
            'origin' => 'https://account.bilibili.com',
            'referer' => 'https://account.bilibili.com/account/big/myPackage',
        ];
        $payload = [];
        $raw = Curl::get('pc', $url, $payload, $headers);
        // {"code":0,"message":"0","ttl":1,"data":{"list":[{"type":1,"state":0,"expire_time":1622476799},{"type":2,"state":0,"expire_time":1622476799}]}}
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0 && isset($de_raw['data']['list'])) {
            Log::info('获取大会员权益列表成功');
            return $de_raw['data']['list'];
        } else {
            Log::warning("获取大会员权益列表失败 $raw");
            return [];
        }
    }
}