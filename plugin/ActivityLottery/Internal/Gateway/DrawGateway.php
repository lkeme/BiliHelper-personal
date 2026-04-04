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
        $this->drawOnceFetcher = $drawOnceFetcher ?? static fn (array $payload): array => ApiActivity::doLottery($payload, max(1, (int)($payload['num'] ?? 1)));
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
    public function drawOnce(array $activity, int $num = 1): array
    {
        $payload = $this->buildPayload($activity);
        $payload['num'] = (string)max(1, $num);
        $response = (array)($this->drawOnceFetcher)($payload);
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
            'page_id' => $this->resolvePageId($activity),
            'url' => trim((string)($activity['url'] ?? '')),
            'title' => trim((string)($activity['title'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $activity
     */
    private function resolvePageId(array $activity): string
    {
        $pageId = trim((string)($activity['page_id'] ?? ''));
        if ($pageId !== '') {
            return $pageId;
        }

        $url = trim((string)($activity['url'] ?? ''));
        if ($url === '') {
            return '';
        }

        $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
        $filename = pathinfo($path, PATHINFO_FILENAME);
        return trim((string)$filename);
    }
}

