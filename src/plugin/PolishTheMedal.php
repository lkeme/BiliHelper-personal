<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Tool\Generator;
use BiliHelper\Util\TimeLock;

class PolishTheMedal
{
    use TimeLock;

    private static int $metal_lock = 0; // 勋章时间锁
    private static array $fans_medals = []; // 全部勋章
    private static array $grey_fans_medals = []; // 灰色勋章

    /**
     * @use run
     */
    public static function run(): void
    {
        if (!getEnable('polish_the_medal')) {
            return;
        }

        // 获取灰色勋章
        if (self::$metal_lock < time()) {
            // 如果勋章过多导致未处理完，就1小时一次，否则10小时一次。
            if (empty(self::$grey_fans_medals)) {
                // 处理每日
                if (getConf('everyday', 'polish_the_medal')) {
                    // 如果是 直接定时到第二天7点
                    self::fetchGreyMedalList(true);
                    self::$metal_lock = time() + self::timing(7, 0, 0, true);
                } else {
                    // 否则按正常逻辑
                    self::fetchGreyMedalList();
                    self::$metal_lock = time() + 10 * 60 * 60;
                }
            } else {
                self::$metal_lock = time() + 60 * 60;
            }
        }
        // 点亮灰色勋章
        if (self::getLock() < time()) {
            // 随机4-10分钟处理一次点亮操作
            self::polishTheMedal();
            self::setLock(mt_rand(4, 10) * 60);
        }
    }

    /**
     * @use 点亮勋章
     */
    private static function polishTheMedal(): void
    {
        $medal = array_pop(self::$grey_fans_medals);
        // 为空
        if (is_null($medal)) return;
        // 特殊房间处理|央视未开播|CODE -> 11000 MSG -> ''
        if (in_array($medal['roomid'], [21686237, 0])) return;

        Log::info("开始点亮直播间@{$medal['roomid']}的勋章");
        // 擦亮
        $response = Live::sendBarrageAPP($medal['roomid'], Generator::emoji());
        if (isset($response['code']) && $response['code'] == 0) {
            Log::notice("在直播间@{$medal['roomid']}发送点亮弹幕成功");
        } else {
            Log::warning("在直播间@{$medal['roomid']}发送点亮弹幕失败, CODE -> {$response['code']} MSG -> {$response['message']} ");
        }
    }


    /**
     * @use 获取灰色勋章列表(过滤无勋章或已满)
     * @param bool $all
     */
    private static function fetchGreyMedalList(bool $all = false): void
    {
        $data = Live::fetchMedalList();
        foreach ($data as $vo) {
            // 过滤主站勋章
            if (!isset($vo['roomid']) || $vo['roomid'] == 0) continue;
            // 过滤自己勋章
            if ($vo['target_id'] == getUid()) continue;
            // 所有
            self::$fans_medals[] = [
                'uid' => $vo['target_id'],
                'roomid' => $vo['roomid'],
            ];
            // 如果是每天擦亮 ，就不过滤|否则过滤掉，只点亮灰色
            if ($all) {
                self::$grey_fans_medals[] = [
                    'uid' => $vo['target_id'],
                    'roomid' => $vo['roomid'],
                ];
            } else {
                //  灰色
                if ($vo['medal_color_start'] == 12632256 && $vo['medal_color_end'] == 12632256 && $vo['medal_color_border'] == 12632256) {
                    self::$grey_fans_medals[] = [
                        'uid' => $vo['target_id'],
                        'roomid' => $vo['roomid'],
                    ];
                }
            }
        }
        // 乱序
        shuffle(self::$grey_fans_medals);
    }
}