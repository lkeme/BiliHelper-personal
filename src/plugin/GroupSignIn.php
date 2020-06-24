<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class GroupSignIn
{
    use TimeLock;

    public static function run()
    {
        if (self::getLock() > time()) {
            return;
        }

        $groups = self::getGroupList();
        if (empty($groups)) {
            self::setLock(self::timing(10));
            return;
        }

        foreach ($groups as $group) {
            self::signInGroup($group);
        }

        self::setLock(8 * 60 * 60);
    }


    /**
     * @use 获取友爱社列表
     * @return array
     */
    protected static function getGroupList(): array
    {
        $url = 'https://api.vc.bilibili.com/link_group/v1/member/my_groups';
        $payload = [];
        $raw = Curl::get('app', $url, Sign::common($payload));
        $de_raw = json_decode($raw, true);

        if (empty($de_raw['data']['list'])) {
            Log::notice('你没有需要签到的应援团!');
            return [];
        }
        return $de_raw['data']['list'];
    }


    /**
     * @use 签到
     * @param array $groupInfo
     * @return bool
     */
    protected static function signInGroup(array $groupInfo): bool
    {
        $url = 'https://api.vc.bilibili.com/link_setting/v1/link_setting/sign_in';
        $payload = [
            'group_id' => $groupInfo['group_id'],
            'owner_id' => $groupInfo['owner_uid'],
        ];
        $raw = Curl::get('app', $url, Sign::common($payload));
        $de_raw = json_decode($raw, true);

        if ($de_raw['code'] != '0') {
            Log::warning('在应援团{' . $groupInfo['group_name'] . '}中签到失败, 原因待查');
            // TODO 任务失败原因
            return false;
        }
        if ($de_raw['data']['status'] == '0') {
            Log::info('在应援团{' . $groupInfo['group_name'] . '}中签到成功,增加{' . $de_raw['data']['add_num'] . '点}亲密度');
        } else {
            Log::notice('在应援团{' . $groupInfo['group_name'] . '}中不要重复签到');
        }

        return true;
    }
}