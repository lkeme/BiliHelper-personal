<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Gateway;

use Bhp\Api\Api\X\ActivityComponents\ApiMission;
use Bhp\Login\AuthFailureClassifier;

final class EraTaskGateway
{
    /**
     * @var callable(string): array<string, mixed>
     */
    private readonly mixed $taskInfoFetcher;
    /**
     * @var callable(string, array<string, mixed>): array<string, mixed>
     */
    private readonly mixed $receiveRewardFetcher;
    private readonly AuthFailureClassifier $authFailureClassifier;

    public function __construct(
        ?callable $taskInfoFetcher = null,
        ?callable $receiveRewardFetcher = null,
        ?AuthFailureClassifier $authFailureClassifier = null,
    ) {
        $this->taskInfoFetcher = $taskInfoFetcher ?? static fn (string $taskId): array => ApiMission::info($taskId);
        $this->receiveRewardFetcher = $receiveRewardFetcher ?? static function (string $taskId, array $payload): array {
            return ApiMission::receive(
                $taskId,
                trim((string)($payload['act_id'] ?? '')),
                trim((string)($payload['act_name'] ?? '')),
                trim((string)($payload['task_name'] ?? '')),
                trim((string)($payload['reward_name'] ?? '')),
                trim((string)($payload['address_id'] ?? '')),
            );
        };
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    /**
     * @return array<string, mixed>
     */
    public function taskInfo(string $taskId): array
    {
        $response = (array)($this->taskInfoFetcher)($taskId);
        $this->authFailureClassifier->assertNotAuthFailure($response, '查询领奖任务时账号未登录');

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function receiveReward(string $taskId, array $payload = []): array
    {
        $response = (array)($this->receiveRewardFetcher)($taskId, $payload);
        $this->authFailureClassifier->assertNotAuthFailure($response, '领取奖励时账号未登录');

        return $response;
    }
}

