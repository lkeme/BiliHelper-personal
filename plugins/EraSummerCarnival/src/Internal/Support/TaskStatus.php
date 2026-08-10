<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Support;

final class TaskStatus
{
    /**
     * 可执行：任务未完成，允许上报计数
     */
    public const RUNNABLE = 1;

    /**
     * 可领奖：任务已达成，等待领取
     */
    public const CLAIMABLE = 2;

    /**
     * 已完结：任务已完成且奖励已领完
     */
    public const FINISHED = 3;

    /**
     * 解析任务状态
     * @param mixed $value
     * @return int
     */
    public static function of(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }
}
