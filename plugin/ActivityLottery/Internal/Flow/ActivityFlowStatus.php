<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

final class ActivityFlowStatus
{
    public const PENDING = 'pending';
    public const RUNNING = 'running';
    public const BLOCKED = 'blocked';
    public const COMPLETED = 'completed';
    public const SKIPPED = 'skipped';
    public const EXPIRED = 'expired';
    public const FAILED = 'failed';
}
