<?php declare(strict_types=1);

namespace Bhp\Scheduler;

final class ScheduledTask
{
    /**
     * 初始化 ScheduledTask
     * @param string $hook
     * @param string $name
     * @param int $priority
     * @param float $intervalSeconds
     * @param string $policy
     * @param int $maxConcurrency
     * @param float $timeoutSeconds
     * @param float $nextRunAtNs
     * @param bool $highFrequency
     * @param bool $bootstrapFirst
     * @param array $governanceHosts
     * @param int $governanceWindowSeconds
     * @param int $governanceMaxRequestsPerHost
     * @param int $governanceCooldownSeconds
     * @param string $governanceGroup
     * @param int $governanceGroupMaxConcurrency
     * @param string $governanceProfile
     * @param float $governanceGroupBackoffSeconds
     * @param float $governanceCooldownMultiplier
     * @param int $failureCount
     * @param float $circuitOpenUntilNs
     */
    public function __construct(
        public readonly string $hook,
        public readonly string $name,
        public readonly int $priority,
        public readonly float $intervalSeconds,
        public readonly string $policy = TaskPolicy::SKIP,
        public readonly int $maxConcurrency = 1,
        public readonly float $timeoutSeconds = 30.0,
        public float $nextRunAtNs = 0.0,
        public readonly bool $highFrequency = false,
        public readonly bool $bootstrapFirst = false,
        /** @var string[] */
        public readonly array $governanceHosts = [],
        public readonly int $governanceWindowSeconds = 0,
        public readonly int $governanceMaxRequestsPerHost = 0,
        public readonly int $governanceCooldownSeconds = 0,
        public readonly string $governanceGroup = '',
        public readonly int $governanceGroupMaxConcurrency = 0,
        public readonly string $governanceProfile = '',
        public readonly float $governanceGroupBackoffSeconds = 0.0,
        public readonly float $governanceCooldownMultiplier = 0.0,
        public int $failureCount = 0,
        public float $circuitOpenUntilNs = 0.0,
    ) {
    }
}
