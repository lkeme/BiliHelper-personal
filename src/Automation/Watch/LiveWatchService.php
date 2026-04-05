<?php declare(strict_types=1);

namespace Bhp\Automation\Watch;

use Bhp\Api\XLive\DataInterface\V1\X25Kn\ApiTrace;
use Bhp\Api\XLive\WebRoom\V1\Index\ApiIndex;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Util\Fake\Fake;

final class LiveWatchService
{
    private readonly ApiIndex $apiIndex;
    private readonly ApiTrace $apiTrace;
    private \Closure $roomEntryAction;
    private \Closure $enterAction;
    private \Closure $heartbeatAction;
    private \Closure $userAgentResolver;
    private \Closure $buvidFactory;
    private \Closure $uuidFactory;
    private AuthFailureClassifier $authFailureClassifier;

    /**
     * @param callable():string $userAgentResolver
     * @param null|callable(int):void $roomEntryAction
     * @param null|callable(int, int, int, int, string, string, string):array<string, mixed> $enterAction
     * @param null|callable(array<string, mixed>, string, int):array<string, mixed> $heartbeatAction
     * @param null|callable():string $buvidFactory
     * @param null|callable():string $uuidFactory
     */
    public function __construct(
        ApiIndex $apiIndex,
        ApiTrace $apiTrace,
        callable $userAgentResolver,
        ?callable $roomEntryAction = null,
        ?callable $enterAction = null,
        ?callable $heartbeatAction = null,
        ?callable $buvidFactory = null,
        ?callable $uuidFactory = null,
        ?AuthFailureClassifier $authFailureClassifier = null,
    ) {
        $this->apiIndex = $apiIndex;
        $this->apiTrace = $apiTrace;
        $this->roomEntryAction = $roomEntryAction !== null
            ? \Closure::fromCallable($roomEntryAction)
            : function (int $roomId): void {
                $this->apiIndex->roomEntryAction($roomId);
            };
        $this->enterAction = $enterAction !== null
            ? \Closure::fromCallable($enterAction)
            : fn (int $roomId, int $ruid, int $parentAreaId, int $areaId, string $buvid, string $uuid, string $userAgent): array => $this->apiTrace->enter($roomId, $ruid, $parentAreaId, $areaId, $buvid, $uuid, $userAgent);
        $this->heartbeatAction = $heartbeatAction !== null
            ? \Closure::fromCallable($heartbeatAction)
            : fn (array $session, string $userAgent, int $interval): array => $this->apiTrace->heartbeat($session, $userAgent, $interval);
        $this->userAgentResolver = \Closure::fromCallable($userAgentResolver);
        $this->buvidFactory = $buvidFactory !== null
            ? \Closure::fromCallable($buvidFactory)
            : static fn (): string => Fake::buvid();
        $this->uuidFactory = $uuidFactory !== null
            ? \Closure::fromCallable($uuidFactory)
            : static fn (): string => Fake::uuid4();
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function start(int $roomId, array $context = []): LiveWatchSession
    {
        $session = LiveWatchSession::start($roomId, array_merge([
            'seq_id' => 0,
            'live_buvid' => ($this->buvidFactory)(),
            'live_uuid' => ($this->uuidFactory)(),
        ], $context));
        $this->assertStartSessionBoundary($session);
        ($this->roomEntryAction)($session->roomId);
        $response = ($this->enterAction)(
            $session->roomId,
            $session->ruid,
            $session->parentAreaId,
            $session->areaId,
            $session->liveBuvid,
            $session->liveUuid,
            ($this->userAgentResolver)(),
        );
        $this->authFailureClassifier->assertNotAuthFailure($response, '直播观看建链时账号未登录');
        if (($response['code'] ?? 0) !== 0) {
            $message = (string)($response['message'] ?? $response['msg'] ?? '');
            throw new \RuntimeException("x25Kn/E失败 {$response['code']} -> {$message}");
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $secretKey = trim((string)($data['secret_key'] ?? ''));
        $secretRule = $data['secret_rule'] ?? [];
        $heartbeatInterval = max(30, (int)($data['heartbeat_interval'] ?? 60));
        $ets = (int)($data['timestamp'] ?? 0);

        if ($secretKey === '' || !is_array($secretRule) || $secretRule === [] || $ets <= 0) {
            throw new \RuntimeException('x25Kn/E返回缺少必要会话字段');
        }

        return $session->with([
            'heartbeat_interval' => $heartbeatInterval,
            'ets' => $ets,
            'secret_key' => $secretKey,
            'secret_rule' => array_values(array_map(static fn (mixed $rule): int => (int)$rule, $secretRule)),
            'last_heartbeat_at' => microtime(true),
        ]);
    }

    public function heartbeat(LiveWatchSession $session): LiveWatchSession
    {
        $this->assertHeartbeatSessionBoundary($session);
        $now = microtime(true);
        $lastHeartbeatAt = max(0.0, $session->lastHeartbeatAt);
        $elapsedSeconds = $lastHeartbeatAt > 0
            ? max(1, (int)floor($now - $lastHeartbeatAt))
            : max(1, $session->heartbeatInterval);

        $request = $session->with([
            '_debug_elapsed_seconds' => $elapsedSeconds,
            '_debug_heartbeat_at' => $now,
        ]);
        $response = ($this->heartbeatAction)(
            $request->toArray(),
            ($this->userAgentResolver)(),
            $session->heartbeatInterval,
        );
        $this->authFailureClassifier->assertNotAuthFailure($response, '直播观看心跳时账号未登录');
        if (($response['code'] ?? 0) !== 0) {
            $message = (string)($response['message'] ?? $response['msg'] ?? '');
            throw new \RuntimeException("x25Kn/X失败 {$response['code']} -> {$message}");
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $nextContext = [
            'seq_id' => $session->seqId + 1,
            'heartbeat_interval' => max(30, (int)($data['heartbeat_interval'] ?? $session->heartbeatInterval)),
            'ets' => (int)($data['timestamp'] ?? $session->ets),
            'last_heartbeat_at' => $now,
            '_debug_elapsed_seconds' => $elapsedSeconds,
            '_debug_heartbeat_at' => $now,
        ];
        $nextSecretKey = trim((string)($data['secret_key'] ?? ''));
        if ($nextSecretKey !== '') {
            $nextContext['secret_key'] = $nextSecretKey;
        }
        if (is_array($data['secret_rule'] ?? null) && $data['secret_rule'] !== []) {
            $nextContext['secret_rule'] = array_values(array_map(static fn (mixed $rule): int => (int)$rule, $data['secret_rule']));
        }

        return $session->with($nextContext);
    }

    private function assertStartSessionBoundary(LiveWatchSession $session): void
    {
        if (
            $session->roomId <= 0
            || $session->ruid <= 0
            || $session->parentAreaId <= 0
            || $session->areaId <= 0
            || trim($session->liveBuvid) === ''
            || trim($session->liveUuid) === ''
        ) {
            throw new \RuntimeException('直播观看会话参数无效');
        }
    }

    private function assertHeartbeatSessionBoundary(LiveWatchSession $session): void
    {
        if (
            $session->roomId <= 0
            || $session->ruid <= 0
            || $session->parentAreaId <= 0
            || $session->areaId <= 0
            ||
            $session->ets <= 0
            || trim($session->secretKey) === ''
            || $session->secretRule === []
            || trim($session->liveBuvid) === ''
            || trim($session->liveUuid) === ''
        ) {
            throw new \RuntimeException('直播观看心跳会话无效');
        }
    }
}
