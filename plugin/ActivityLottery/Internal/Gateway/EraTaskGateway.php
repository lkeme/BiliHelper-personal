<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Gateway;

use Bhp\Api\Api\X\ActivityComponents\ApiMission;

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

    public function __construct(
        ?callable $taskInfoFetcher = null,
        ?callable $receiveRewardFetcher = null,
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
    }

    /**
     * @return array<string, mixed>
     */
    public function taskInfo(string $taskId): array
    {
        return (array)($this->taskInfoFetcher)($taskId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function receiveReward(string $taskId, array $payload = []): array
    {
        return (array)($this->receiveRewardFetcher)($taskId, $payload);
    }
}

