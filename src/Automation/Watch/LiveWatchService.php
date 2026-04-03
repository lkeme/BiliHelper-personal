<?php declare(strict_types=1);

namespace Bhp\Automation\Watch;

use Bhp\Api\XLive\DataInterface\V1\X25Kn\ApiTrace;
use Bhp\Api\XLive\WebRoom\V1\Index\ApiIndex;
use Bhp\Runtime\Runtime;
use Bhp\Util\Fake\Fake;

final class LiveWatchService
{
    private \Closure $roomEntryAction;
    private \Closure $enterAction;
    private \Closure $heartbeatAction;
    private \Closure $userAgentResolver;
    private \Closure $buvidFactory;
    private \Closure $uuidFactory;

    /**
     * @param null|callable(int):void $roomEntryAction
     * @param null|callable(int, int, int, int, string, string, string):array<string, mixed> $enterAction
     * @param null|callable(array<string, mixed>, string, int):array<string, mixed> $heartbeatAction
     * @param null|callable():string $userAgentResolver
     * @param null|callable():string $buvidFactory
     * @param null|callable():string $uuidFactory
     */
    public function __construct(
        ?callable $roomEntryAction = null,
        ?callable $enterAction = null,
        ?callable $heartbeatAction = null,
        ?callable $userAgentResolver = null,
        ?callable $buvidFactory = null,
        ?callable $uuidFactory = null,
    ) {
        $this->roomEntryAction = $roomEntryAction !== null
            ? \Closure::fromCallable($roomEntryAction)
            : static function (int $roomId): void {
                ApiIndex::roomEntryAction($roomId);
            };
        $this->enterAction = $enterAction !== null
            ? \Closure::fromCallable($enterAction)
            : static fn (int $roomId, int $ruid, int $parentAreaId, int $areaId, string $buvid, string $uuid, string $userAgent): array => ApiTrace::enter($roomId, $ruid, $parentAreaId, $areaId, $buvid, $uuid, $userAgent);
        $this->heartbeatAction = $heartbeatAction !== null
            ? \Closure::fromCallable($heartbeatAction)
            : static fn (array $session, string $userAgent, int $interval): array => ApiTrace::heartbeat($session, $userAgent, $interval);
        $this->userAgentResolver = $userAgentResolver !== null
            ? \Closure::fromCallable($userAgentResolver)
            : static fn (): string => (string)Runtime::getInstance()->appContext()->device('platform.headers.pc_ua');
        $this->buvidFactory = $buvidFactory !== null
            ? \Closure::fromCallable($buvidFactory)
            : static fn (): string => Fake::buvid();
        $this->uuidFactory = $uuidFactory !== null
            ? \Closure::fromCallable($uuidFactory)
            : static fn (): string => Fake::uuid4();
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
        if (($response['code'] ?? 0) !== 0) {
            $message = (string)($response['message'] ?? $response['msg'] ?? '');
            throw new \RuntimeException("x25Kn/E失败 {$response['code']} -> {$message}");
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $secretKey = trim((string)($data['secret_key'] ?? ''));
        $secretRule = $data['secret_rule'] ?? [];
        $heartbeatInterval = max(30, (int)($data['heartbeat_interval'] ?? 60));
        $ets = (int)($data['timestamp'] ?? 0);

        if ($secretKey === '' || !is_array($secretRule) || $ets <= 0) {
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
}
