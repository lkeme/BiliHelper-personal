<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Gateway;

use Bhp\Api\Api\X\Activity\ApiActivity;

final class DrawGateway
{
    /**
     * @var callable(array<string, string>): array<string, mixed>
     */
    private readonly mixed $refreshTimesFetcher;
    /**
     * @var callable(array<string, string>): array<string, mixed>
     */
    private readonly mixed $drawOnceFetcher;

    public function __construct(
        ?callable $refreshTimesFetcher = null,
        ?callable $drawOnceFetcher = null,
    ) {
        $this->refreshTimesFetcher = $refreshTimesFetcher ?? static fn (array $payload): array => ApiActivity::myTimes($payload);
        $this->drawOnceFetcher = $drawOnceFetcher ?? static fn (array $payload): array => ApiActivity::doLottery($payload);
    }

    /**
     * @param array<string, mixed> $activity
     * @return array<string, mixed>
     */
    public function refreshTimes(array $activity): array
    {
        return (array)($this->refreshTimesFetcher)($this->buildPayload($activity));
    }

    /**
     * @param array<string, mixed> $activity
     * @return array<string, mixed>
     */
    public function drawOnce(array $activity): array
    {
        return (array)($this->drawOnceFetcher)($this->buildPayload($activity));
    }

    /**
     * @param array<string, mixed> $activity
     * @return array<string, string>
     */
    private function buildPayload(array $activity): array
    {
        return [
            'sid' => trim((string)($activity['lottery_id'] ?? $activity['sid'] ?? '')),
            'url' => trim((string)($activity['url'] ?? '')),
            'title' => trim((string)($activity['title'] ?? '')),
        ];
    }
}

