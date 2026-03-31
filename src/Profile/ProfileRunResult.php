<?php declare(strict_types=1);

namespace Bhp\Profile;

final class ProfileRunResult
{
    /**
     * @param string[] $command
     */
    public function __construct(
        public readonly string $profile,
        public readonly array $command,
        public readonly int $exitCode,
        public readonly float $durationSeconds,
        public readonly string $stdout,
        public readonly string $stderr,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }
}
