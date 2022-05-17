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
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class DailyTask
{
    use TimeLock;

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('daily_task')) {
            return;
        }

        $data = self::check();
        // if (isset($data['data']['double_watch_info'])) {
        //     self::double_watch_info($data['data']['double_watch_info']);
        // }
        if (isset($data['data']['sign_info'])) {
            self::sign_info($data['data']['sign_info']);
        }
        self::setLock(mt_rand(8, 12) * 60 * 60);
    }

    /**
     * @use 检查每日任务
     * @return mixed
     */
    private static function check(): mixed
    {
        $url = 'https://api.live.bilibili.com/i/api/taskInfo';
        $payload = [];
        $data = Curl::get('app', $url, Sign::common($payload));
        $data = json_decode($data, true);
        Log::info('正在检查每日任务...');
        if (isset($data['code']) && $data['code']) {
            Log::warning('每日任务检查失败!', ['msg' => $data['message']]);
        }
        return $data;
    }

    /**
     * @use 每日签到
     * @param $info
     */
    private static function sign_info($info): void
    {
        Log::info('检查任务「每日签到」...');

        if ($info['status'] == 1) {
            Log::notice('该任务已完成');
            return;
        }
        $url = 'https://api.live.bilibili.com/xlive/web-ucenter/v1/sign/DoSign';
        $headers = [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index'
        ];
        $url = 'https://api.live.bilibili.com/sign/doSign';
        $payload = [];
        $data = Curl::get('app', $url, Sign::common($payload));
        $data = json_decode($data, true);
        // 您被封禁了,无法进行操作
        // {"code":1011040,"message":"今日已签到过,无法重复签到","ttl":1,"data":null}
        // {"code":0,"message":"0","ttl":1,"data":{"text":"3000点用户经验,2根辣条","specialText":"再签到3天可以获得666银瓜子","allDays":31,"hadSignDays":2,"isBonusDay":0}}
        // {"code":0,"message":"0","ttl":1,"data":{"text":"3000点用户经验,2根辣条,50根辣条","specialText":"","allDays":31,"hadSignDays":20,"isBonusDay":1}}
        if (isset($data['code']) && $data['code']) {
            Log::warning("签到失败: {$data['message']}");
        } else {
            Log::notice("签到成功: {$data['data']['text']}");
            // 推送签到信息
            Notice::push('todaySign', $data['data']['text']);
        }
    }

    /**
     * @use 双端任务
     * @param $info
     */
    private static function double_watch_info($info): void
    {
        Log::info('检查任务「双端观看直播」...');

        if ($info['status'] == 2) {
            Log::notice('已经领取奖励');
            return;
        }
        if ($info['mobile_watch'] != 1 || $info['web_watch'] != 1) {
            Log::notice('任务未完成，请等待');
            return;
        }
        $url = 'https://api.live.bilibili.com/activity/v1/task/receive_award';
        $payload = [
            'task_id' => 'double_watch_task',
        ];
        $data = Curl::post('app', $url, Sign::common($payload));
        $data = json_decode($data, true);

        if (isset($data['code']) && $data['code']) {
            Log::warning("「双端观看直播」任务奖励领取失败，{$data['message']}!");
        } else {
            Log::info('奖励领取成功!');
            foreach ($info['awards'] as $vo) {
                Log::notice(sprintf("获得 %s × %d", $vo['name'], $vo['num']));
            }
        }
    }
}
