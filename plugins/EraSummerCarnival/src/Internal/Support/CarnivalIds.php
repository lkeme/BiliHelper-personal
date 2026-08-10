<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Support;

/**
 * 活动硬编码标识兜底集中处。
 *
 * 运行时优先从活动页快照解析（见 CarnivalPageResolver），
 * 仅在解析失败或关键字段缺失时回落到这里，并记 warning。
 *
 * 数据来源：2026-08-10 实测活动页 __BILIACT_EVAPAGEDATA__ 与组件 bundle。
 */
final class CarnivalIds
{
    public const ACTIVITY_URL = 'https://www.bilibili.com/blackboard/era/5QLqxye7tDmgI73D.html';
    public const ACTIVITY_ID = '1ERAzwloghvbsf00';
    public const ACTIVITY_NAME = '2026“次元奇旅”暑期狂欢节';
    public const PAGE_ID = '3ERAcwloghvqsw00';
    public const LOTTERY_ID = '4ERA4wloghvmpk00';

    /**
     * 板块内直播间运营位
     */
    public const LIVE_SOURCE_ID = '29ERA1wloghvjnu00';

    /**
     * 签到有礼
     */
    public const SIGN_IN_TASK_ID = '6ERAxwloghv00a00';
    public const SIGN_IN_COUNTER = '6ERA61wd0t';
    public const SIGN_IN_TASK_NAME = '签到有礼';

    /**
     * 每日板块内直播间观看五分钟
     */
    public const WATCH_LIVE_TASK_ID = '6ERAcwloghvroi00';
    public const WATCH_LIVE_COUNTER = '6ERA142hs7';
    public const WATCH_LIVE_TARGET_SECONDS = 300;

    /**
     * 每日板块内直播间发送弹幕五条 —— 本插件不实现，仅登记以免误判为未知任务
     */
    public const DANMAKU_TASK_ID = '6ERAcwloghvr5p00';

    /**
     * 累计签到 checkpoint：sid => [天数阈值, 奖励名]
     *
     * @return array<string, array{days: int, reward_name: string}>
     */
    public static function signInCheckpoints(): array
    {
        return [
            '18ERA2wloghvomo00' => ['days' => 7, 'reward_name' => '抽奖券-抽奖次数*10'],
            '18ERA2wloghvom700' => ['days' => 14, 'reward_name' => '抽奖券-抽奖次数*20'],
            '18ERA2wloghvppu00' => ['days' => 30, 'reward_name' => '抽奖券-抽奖次数*30'],
            '18ERA2wloghvooc00' => ['days' => 45, 'reward_name' => '大会员月卡（限量2026份）'],
        ];
    }

    /**
     * 关注类任务：task_id => [counter, 任务名]
     *
     * @return array<string, array{counter: string, task_name: string}>
     */
    public static function followTasks(): array
    {
        return [
            '6ERAxwloghv0j000' => ['counter' => '6ERAfkf808', 'task_name' => '关注优质VLOG稿件UP主，得抽奖次数'],
            '6ERAcwloghvuni00' => ['counter' => '6ERAlpxrgy', 'task_name' => '关注优质稿件UP主，得抽奖次数（每周刷新）'],
            '6ERAxwloghv0ll00' => ['counter' => '6ERAx1bqqw', 'task_name' => '活动期间关注页面内合作品牌官号，得抽奖次数'],
        ];
    }

    /**
     * 需要纳入 totalv2 批量查询的任务 ID
     *
     * @return string[]
     */
    public static function trackedTaskIds(): array
    {
        return array_values(array_unique(array_merge(
            [self::SIGN_IN_TASK_ID, self::WATCH_LIVE_TASK_ID],
            array_keys(self::followTasks()),
        )));
    }
}
