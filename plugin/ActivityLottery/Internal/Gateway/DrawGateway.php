<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Gateway;

use Bhp\Api\Api\X\Activity\ApiActivity;
use Bhp\Login\AuthFailureClassifier;

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
    private readonly AuthFailureClassifier $authFailureClassifier;

    public function __construct(
        ?callable $refreshTimesFetcher = null,
        ?callable $drawOnceFetcher = null,
        ?AuthFailureClassifier $authFailureClassifier = null,
    ) {
        $this->refreshTimesFetcher = $refreshTimesFetcher ?? static fn (array $payload): array => ApiActivity::myTimes($payload);
        $this->drawOnceFetcher = $drawOnceFetcher ?? static fn (array $payload): array => ApiActivity::doLottery($payload);
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    /**
     * @param array<string, mixed> $activity
     * @return array<string, mixed>
     */
    public function refreshTimes(array $activity): array
    {
        $response = (array)($this->refreshTimesFetcher)($this->buildPayload($activity));
        $this->authFailureClassifier->assertNotAuthFailure($response, '刷新抽奖次数时账号未登录');

        return $response;
    }

    /**
     * @param array<string, mixed> $activity
     * @return array<string, mixed>
     */
    public function drawOnce(array $activity): array
    {
        $response = (array)($this->drawOnceFetcher)($this->buildPayload($activity));
        $this->authFailureClassifier->assertNotAuthFailure($response, '执行抽奖时账号未登录');

        return $response;
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

