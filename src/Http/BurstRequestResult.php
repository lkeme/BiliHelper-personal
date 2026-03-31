<?php declare(strict_types=1);

namespace Bhp\Http;

final class BurstRequestResult
{
    /**
     * @param array<int, array<int|string, HttpResponse>> $waves
     */
    public function __construct(
        private readonly array $waves,
        private readonly bool $matched,
        private readonly int $matchedWave,
    ) {
    }

    public function waves(): array
    {
        return $this->waves;
    }

    public function matched(): bool
    {
        return $this->matched;
    }

    public function matchedWave(): int
    {
        return $this->matchedWave;
    }
}
