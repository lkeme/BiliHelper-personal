<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

final class ActivityNodeStatus
{
    public const PENDING = 'pending';
    public const RUNNING = 'running';
    public const WAITING = 'waiting';
    public const SUCCEEDED = 'succeeded';
    public const SKIPPED = 'skipped';
    public const FAILED = 'failed';
}
