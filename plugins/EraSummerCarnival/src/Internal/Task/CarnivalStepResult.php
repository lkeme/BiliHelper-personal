<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task;

/**
 * 单个任务执行结果。
 *
 * failed=true 才代表业务失败（由编排层转成 TaskResult::retryAfter，计入调度器熔断）；
 * executed=false 表示本轮无事可做，不应被当成失败。
 */
final class CarnivalStepResult
{
    private function __construct(
        public readonly bool $executed,
        public readonly bool $failed,
        public readonly ?float $delaySeconds,
        public readonly ?string $message,
    ) {
    }

    /**
     * 本轮无需执行
     */
    public static function skipped(?string $message = null): self
    {
        return new self(false, false, null, $message);
    }

    /**
     * 已执行且本项完结
     */
    public static function done(?string $message = null): self
    {
        return new self(true, false, null, $message);
    }

    /**
     * 已执行但需按指定间隔继续（如观看心跳）
     */
    public static function again(float $delaySeconds, ?string $message = null): self
    {
        return new self(true, false, max(0.0, $delaySeconds), $message);
    }

    /**
     * 本轮不可执行，建议延后重试（非失败，如板块内无在播直播间）
     */
    public static function postponed(float $delaySeconds, ?string $message = null): self
    {
        return new self(false, false, max(0.0, $delaySeconds), $message);
    }

    /**
     * 业务失败，计入熔断
     */
    public static function failed(float $delaySeconds, string $message): self
    {
        return new self(true, true, max(0.0, $delaySeconds), $message);
    }
}
