<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 *  LastAPIChecked: null
 */

namespace lkeme\BiliHelper;

class GroupSignIn
{
    public static $lock = 0;

    // RUN
    public static function run()
    {
        if (self::$lock > time()) {
            return;
        }

        $groups = self::getGroupList();
        if (empty($groups)) {
            self::$lock = time() + 24 * 60 * 60;
            return;
        }

        foreach ($groups as $group) {
            self::signInGroup($group);
        }

        self::$lock = time() + 8 * 60 * 60;
    }

    //GROUP LIST
    protected static function getGroupList(): array
    {
        $payload = [];
        $raw = Curl::get('https://api.vc.bilibili.com/link_group/v1/member/my_groups', Sign::api($payload));
        $de_raw = json_decode($raw, true);

        if (empty($de_raw['data']['list'])) {
            Log::notice('你没有需要签到的应援团!');
            return [];
        }
        return $de_raw['data']['list'];
    }

    //SIGN IN
    protected static function signInGroup(array $groupInfo): bool
    {
        $payload = [
            'group_id' => $groupInfo['group_id'],
            'owner_id' => $groupInfo['owner_uid'],
        ];
        $raw = Curl::get('https://api.vc.bilibili.com/link_setting/v1/link_setting/sign_in', Sign::api($payload));
        $de_raw = json_decode($raw, true);

        if ($de_raw['code'] != '0') {
            Log::warning('在应援团{' . $groupInfo['group_name'] . '}中签到失败,原因待查');
            // TODO
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