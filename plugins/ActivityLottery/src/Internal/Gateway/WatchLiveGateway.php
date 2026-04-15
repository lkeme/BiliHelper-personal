<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway;

use Bhp\Api\XLive\WebInterface\V1\Second\ApiList;
use Bhp\Api\XLive\WebInterface\V1\WebMain\ApiRecommend;
use Bhp\Api\XLive\WebRoom\V1\Index\ApiIndex;
use Bhp\Automation\Watch\LiveWatchService;
use Bhp\Automation\Watch\LiveWatchSession;
use Bhp\Util\Exceptions\RequestException;

final class WatchLiveGateway
{
    private readonly ApiList $apiList;
    private readonly ApiRecommend $apiRecommend;
    private readonly ApiIndex $apiIndex;
    private bool $areaFetchFailureReported = false;
    private bool $directRoomLookupFailed = false;
    private bool $areaListLookupFailed = false;
    private bool $areaListLookupSucceeded = false;
    private bool $recommendLookupFailed = false;
    private bool $recommendLookupSucceeded = false;
    private ?LiveWatchService $watchService = null;
    /** @var array<int, true> */
    private array $excludedRoomIds = [];
    /** @var array<string, string> */
    private array $areaWebIds = [];
    /**
     * @var callable(array<int, string>, int, int): ?array<string, mixed>
     */
    private readonly mixed $startAction;
    /**
     * @var callable(array<string, mixed>): array<string, mixed>
     */
    private readonly mixed $heartbeatAction;
    /**
     * @var callable(string): string
     */
    private readonly mixed $areaTagPageFetcher;
    /**
     * @var \Closure(): LiveWatchService
     */
    private \Closure $watchServiceFactory;
    /** @var \Closure(int):(?array<string, int|string>) */
    private \Closure $roomResolver;
    /** @var \Closure(int, int):(?array<string, int|string>) */
    private \Closure $areaRoomPicker;
    private \Closure $logger;

    /**
     * 初始化 WatchLiveGateway
     * @param ApiList $apiList
     * @param ApiRecommend $apiRecommend
     * @param ApiIndex $apiIndex
     * @param callable $startAction
     * @param callable $heartbeatAction
     * @param LiveWatchService $watchService
     * @param callable $roomResolver
     * @param callable $areaRoomPicker
     * @param callable $areaTagPageFetcher
     * @param callable $userAgentResolver
     * @param callable $logger
     */
    public function __construct(
        ApiList $apiList,
        ApiRecommend $apiRecommend,
        ApiIndex $apiIndex,
        ?callable $startAction = null,
        ?callable $heartbeatAction = null,
        ?LiveWatchService $watchService = null,
        ?callable $roomResolver = null,
        ?callable $areaRoomPicker = null,
        ?callable $areaTagPageFetcher = null,
        ?callable $userAgentResolver = null,
        ?callable $logger = null,
    ) {
        $this->apiList = $apiList;
        $this->apiRecommend = $apiRecommend;
        $this->apiIndex = $apiIndex;
        $this->watchService = $watchService;
        $this->logger = $logger !== null
            ? \Closure::fromCallable($logger)
            : static function (string $level, string $message, array $context = []): void {
            };
        $this->watchServiceFactory = $watchService instanceof LiveWatchService
            ? static fn (): LiveWatchService => $watchService
            : ($userAgentResolver !== null
                ? static fn (): LiveWatchService => throw new \LogicException('WatchLiveGateway requires an explicit LiveWatchService.')
                : static function (): LiveWatchService {
                    throw new \LogicException('WatchLiveGateway requires an explicit LiveWatchService or userAgentResolver.');
                });
        $this->areaTagPageFetcher = $areaTagPageFetcher ?? static fn (string $url): string => '';
        $this->roomResolver = $roomResolver !== null
            ? \Closure::fromCallable($roomResolver)
            : function (int $roomId): ?array {
                return $this->resolveLiveRoom($roomId);
            };
        $this->areaRoomPicker = $areaRoomPicker !== null
            ? \Closure::fromCallable($areaRoomPicker)
            : function (int $areaId, int $parentAreaId): ?array {
                $areaRoom = $this->pickAreaLiveRoom($areaId, $parentAreaId);
                if ($areaRoom !== null) {
                    return $areaRoom;
                }

                return $this->pickRecommendedAreaLiveRoom($areaId, $parentAreaId);
            };

        $this->startAction = $startAction ?? function (array $roomIds, int $areaId = 0, int $parentAreaId = 0) use ($watchService): ?array {
            $watchService = $this->watchService();
            $room = $this->pickLiveRoom($roomIds, $areaId, $parentAreaId);
            if ($room === null) {
                return null;
            }

            $session = $watchService->start(
                (int)$room['room_id'],
                [
                    'ruid' => (int)$room['ruid'],
                    'parent_area_id' => (int)$room['parent_area_id'],
                    'area_id' => (int)$room['area_id'],
                    'room_title' => (string)($room['room_title'] ?? ''),
                    'room_uname' => (string)($room['room_uname'] ?? ''),
                    'room_pick_source' => (string)($room['pick_source'] ?? 'room'),
                ],
            );

            return $session->toArray();
        };
        $this->heartbeatAction = $heartbeatAction ?? fn (array $session): array => $this->watchService()->heartbeat(LiveWatchSession::fromArray($session))->toArray();
    }

    /**
     * @param string[] $roomIds
     * @param int[] $excludedRoomIds
     * @return array<string, mixed>|null
     */
    public function start(array $roomIds, int $areaId = 0, int $parentAreaId = 0, array $excludedRoomIds = []): ?array
    {
        $this->resetLookupState();
        $this->excludedRoomIds = $this->normalizeExcludedRoomIds($excludedRoomIds);
        try {
            $session = ($this->startAction)($roomIds, $areaId, $parentAreaId);
        } catch (\RuntimeException $exception) {
            $retryable = $this->resolveWatchRuntimeFailure($exception);
            if ($retryable instanceof RequestException) {
                throw $retryable;
            }

            throw $exception;
        } finally {
            $this->excludedRoomIds = [];
        }

        if (is_array($session)) {
            return $session;
        }

        $failure = $this->resolvePendingStartFailure();
        if ($failure instanceof RequestException) {
            throw $failure;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    public function heartbeat(array $session): array
    {
        try {
            $nextSession = ($this->heartbeatAction)($session);
        } catch (\RuntimeException $exception) {
            $retryable = $this->resolveWatchRuntimeFailure($exception);
            if ($retryable instanceof RequestException) {
                throw $retryable;
            }

            throw $exception;
        }

        return is_array($nextSession) ? $nextSession : [];
    }

    /**
     * @param string[] $roomIds
     * @return array<string, int|string>|null
     */
    private function pickLiveRoom(array $roomIds, int $areaId = 0, int $parentAreaId = 0): ?array
    {
        $normalizedRoomIds = array_values(array_unique(array_map(static fn (mixed $roomId): int => (int)$roomId, $roomIds)));

        foreach ($normalizedRoomIds as $roomId) {
            if ($roomId <= 0) {
                continue;
            }

            $room = ($this->roomResolver)($roomId);
            if ($room !== null && !$this->isExcludedRoomId((int)($room['room_id'] ?? 0))) {
                return $room;
            }
        }

        if ($areaId <= 0) {
            return null;
        }

        return ($this->areaRoomPicker)($areaId, $parentAreaId);
    }

    /**
     * @return array<string, int|string>|null
     */
    private function pickAreaLiveRoom(int $areaId, int $parentAreaId): ?array
    {
        $parentAreaId = $parentAreaId > 0 ? $parentAreaId : $areaId;
        $webId = $this->resolveAreaWebId($areaId, $parentAreaId);
        $seedResponse = $this->apiList->getList($parentAreaId, $areaId, 1, '', $webId);
        $sortTypes = [''];

        if (($seedResponse['code'] ?? 0) === 0) {
            $this->areaListLookupSucceeded = true;
            foreach (($seedResponse['data']['new_tags'] ?? []) as $tag) {
                if (!is_array($tag)) {
                    continue;
                }

                $sortType = trim((string)($tag['sort_type'] ?? ''));
                if ($sortType !== '' && !in_array($sortType, $sortTypes, true)) {
                    $sortTypes[] = $sortType;
                }
            }
        }

        foreach ($sortTypes as $sortType) {
            for ($page = 1; $page <= 2; $page++) {
                $response = $page === 1 && $sortType === ''
                    ? $seedResponse
                    : $this->apiList->getList($parentAreaId, $areaId, $page, $sortType, $webId);
                if (($response['code'] ?? 0) !== 0) {
                    $this->areaListLookupFailed = true;
                    $this->logAreaListFailure($areaId, $parentAreaId, $sortType, $page, $response);
                    continue;
                }

                $this->areaListLookupSucceeded = true;

                $list = $response['data']['list'] ?? [];
                if (!is_array($list) || $list === []) {
                    $this->log('debug', sprintf(
                        'ERA直播分区拉流失败 area=%d parent=%d sort=%s page=%d code=%s message=%s',
                        $areaId,
                        $parentAreaId,
                        $sortType !== '' ? $sortType : '-',
                        $page,
                        (string)($response['code'] ?? ''),
                        'empty_list'
                    ));
                    continue;
                }

                foreach ($list as $room) {
                    if (!is_array($room)) {
                        continue;
                    }

                    $liveStatus = (int)($room['live_status'] ?? 1);
                    if ($liveStatus !== 1 && $liveStatus !== 2) {
                        continue;
                    }

                    $candidateRoomId = (int)($room['roomid'] ?? $room['room_id'] ?? 0);
                    $candidateRuid = (int)($room['uid'] ?? 0);
                    if ($candidateRoomId <= 0 || $candidateRuid <= 0) {
                        continue;
                    }
                    if ($this->isExcludedRoomId($candidateRoomId)) {
                        continue;
                    }

                    $candidateAreaId = (int)($room['area_id'] ?? $room['area_v2_id'] ?? 0);
                    if ($candidateAreaId !== $areaId) {
                        continue;
                    }

                    $candidateParentAreaId = (int)($room['parent_id'] ?? $room['area_v2_parent_id'] ?? 0);
                    if ($parentAreaId > 0 && $candidateParentAreaId > 0 && $candidateParentAreaId !== $parentAreaId) {
                        continue;
                    }

                    return [
                        'room_id' => $candidateRoomId,
                        'ruid' => $candidateRuid,
                        'parent_area_id' => $candidateParentAreaId > 0 ? $candidateParentAreaId : $parentAreaId,
                        'area_id' => $candidateAreaId,
                        'room_title' => trim((string)($room['title'] ?? '')),
                        'room_uname' => trim((string)($room['uname'] ?? '')),
                        'pick_source' => $sortType === '' ? 'area_list' : 'area_tag_list',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * 解析AreaWebId
     * @param int $areaId
     * @param int $parentAreaId
     * @return string
     */
    private function resolveAreaWebId(int $areaId, int $parentAreaId): string
    {
        $cacheKey = $parentAreaId . ':' . $areaId;
        if (isset($this->areaWebIds[$cacheKey])) {
            return $this->areaWebIds[$cacheKey];
        }

        $url = sprintf(
            'https://live.bilibili.com/p/eden/area-tags?areaId=%d&parentAreaId=%d',
            $areaId,
            $parentAreaId > 0 ? $parentAreaId : $areaId
        );

        try {
            $html = (string)($this->areaTagPageFetcher)($url);
            if (preg_match('/"access_id":"([^"]+)"/', $html, $matches) === 1) {
                return $this->areaWebIds[$cacheKey] = trim((string)$matches[1]);
            }
        } catch (RequestException $exception) {
            $this->log('debug', sprintf(
                'ERA直播分区页 access_id 获取失败 area=%d parent=%d message=%s',
                $areaId,
                $parentAreaId,
                $exception->getMessage()
            ));
        } catch (\Throwable $throwable) {
            $this->log('debug', sprintf(
                'ERA直播分区页 access_id 获取失败 area=%d parent=%d message=%s',
                $areaId,
                $parentAreaId,
                $throwable->getMessage()
            ));
        }

        return $this->areaWebIds[$cacheKey] = '';
    }

    /**
     * @return array<string, int|string>|null
     */
    private function pickRecommendedAreaLiveRoom(int $areaId, int $parentAreaId): ?array
    {
        $response = $this->apiRecommend->getMoreRecList();
        if (($response['code'] ?? 0) !== 0) {
            $this->recommendLookupFailed = true;
            $this->log('debug', sprintf(
                'ERA直播推荐接口异常 area=%d parent=%d code=%s message=%s',
                $areaId,
                $parentAreaId,
                (string)($response['code'] ?? ''),
                (string)($response['message'] ?? $response['msg'] ?? '')
            ));
            return null;
        }

        $this->recommendLookupSucceeded = true;

        $recommendRoomList = $response['data']['recommend_room_list'] ?? [];
        if (!is_array($recommendRoomList)) {
            return null;
        }

        foreach ($recommendRoomList as $room) {
            if (!is_array($room)) {
                continue;
            }

            $candidateAreaId = (int)($room['area_v2_id'] ?? 0);
            $candidateParentAreaId = (int)($room['area_v2_parent_id'] ?? 0);
            if ($candidateAreaId !== $areaId) {
                continue;
            }

            if ($parentAreaId > 0 && $candidateParentAreaId > 0 && $candidateParentAreaId !== $parentAreaId) {
                continue;
            }

            $candidateRoomId = (int)($room['roomid'] ?? 0);
            $candidateRuid = (int)($room['uid'] ?? 0);
            if ($candidateRoomId <= 0 || $candidateRuid <= 0 || $candidateAreaId <= 0) {
                continue;
            }
            if ($this->isExcludedRoomId($candidateRoomId)) {
                continue;
            }

            return [
                'room_id' => $candidateRoomId,
                'ruid' => $candidateRuid,
                'parent_area_id' => $candidateParentAreaId > 0 ? $candidateParentAreaId : $parentAreaId,
                'area_id' => $candidateAreaId,
                'room_title' => trim((string)($room['title'] ?? '')),
                'room_uname' => trim((string)($room['uname'] ?? '')),
                'pick_source' => 'area_recommend',
            ];
        }

        return null;
    }

    /**
     * @return array<string, int|string>|null
     */
    private function resolveLiveRoom(int $roomId): ?array
    {
        if ($roomId <= 0) {
            return null;
        }

        $response = $this->apiIndex->getInfoByRoom($roomId);
        if (($response['code'] ?? 0) !== 0) {
            $this->directRoomLookupFailed = true;
            $this->log('debug', sprintf(
                'ERA直播房间信息接口异常 room=%d code=%s message=%s',
                $roomId,
                (string)($response['code'] ?? ''),
                (string)($response['message'] ?? $response['msg'] ?? '')
            ));
            return null;
        }

        $roomInfo = is_array($response['data']['room_info'] ?? null) ? $response['data']['room_info'] : [];
        if ((int)($roomInfo['live_status'] ?? 0) !== 1) {
            return null;
        }

        $actualRoomId = (int)($roomInfo['room_id'] ?? $roomId);
        $ruid = (int)($roomInfo['uid'] ?? 0);
        $parentAreaId = (int)($roomInfo['parent_area_id'] ?? 0);
        $areaId = (int)($roomInfo['area_id'] ?? 0);
        if ($actualRoomId <= 0 || $ruid <= 0 || $parentAreaId <= 0 || $areaId <= 0) {
            return null;
        }

        return [
            'room_id' => $actualRoomId,
            'ruid' => $ruid,
            'parent_area_id' => $parentAreaId,
            'area_id' => $areaId,
            'room_title' => trim((string)($roomInfo['title'] ?? '')),
            'room_uname' => trim((string)($response['data']['anchor_info']['base_info']['uname'] ?? '')),
            'pick_source' => 'room',
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function logAreaListFailure(int $areaId, int $parentAreaId, string $sortType, int $page, array $response): void
    {
        $message = sprintf(
            'ERA直播分区接口异常 area=%d parent=%d sort=%s page=%d code=%s message=%s',
            $areaId,
            $parentAreaId,
            $sortType !== '' ? $sortType : '-',
            $page,
            (string)($response['code'] ?? ''),
            (string)($response['message'] ?? $response['msg'] ?? '')
        );

        if (!$this->areaFetchFailureReported) {
            $this->log('warning', $message);
            $this->areaFetchFailureReported = true;
            return;
        }

        $this->log('debug', $message);
    }

    /**
     * 处理观看服务
     * @return LiveWatchService
     */
    private function watchService(): LiveWatchService
    {
        if ($this->watchService instanceof LiveWatchService) {
            return $this->watchService;
        }

        $this->watchService = ($this->watchServiceFactory)();

        return $this->watchService;
    }

    /**
     * 处理resetLookup状态
     * @return void
     */
    private function resetLookupState(): void
    {
        $this->directRoomLookupFailed = false;
        $this->areaListLookupFailed = false;
        $this->areaListLookupSucceeded = false;
        $this->recommendLookupFailed = false;
        $this->recommendLookupSucceeded = false;
    }

    /**
     * 解析待处理Start失败
     * @return ?RequestException
     */
    private function resolvePendingStartFailure(): ?RequestException
    {
        if ($this->directRoomLookupFailed) {
            return new RequestException('ERA直播房间信息接口异常，稍后重试');
        }

        if ($this->areaListLookupFailed && !$this->areaListLookupSucceeded) {
            return new RequestException('ERA直播分区列表接口异常，稍后重试');
        }

        if ($this->recommendLookupFailed && !$this->recommendLookupSucceeded && !$this->areaListLookupSucceeded) {
            return new RequestException('ERA直播推荐接口异常，稍后重试');
        }

        return null;
    }

    /**
     * 解析观看运行时失败
     * @param \RuntimeException $exception
     * @return ?RequestException
     */
    private function resolveWatchRuntimeFailure(\RuntimeException $exception): ?RequestException
    {
        $message = trim($exception->getMessage());
        foreach (['x25Kn/E失败', 'x25Kn/X失败'] as $prefix) {
            if (str_starts_with($message, $prefix)) {
                return new RequestException($message);
            }
        }

        return null;
    }

    /**
     * @param int[] $roomIds
     * @return array<int, true>
     */
    private function normalizeExcludedRoomIds(array $roomIds): array
    {
        $normalized = [];
        foreach ($roomIds as $roomId) {
            $normalizedRoomId = (int)$roomId;
            if ($normalizedRoomId > 0) {
                $normalized[$normalizedRoomId] = true;
            }
        }

        return $normalized;
    }

    private function isExcludedRoomId(int $roomId): bool
    {
        return $roomId > 0 && isset($this->excludedRoomIds[$roomId]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        ($this->logger)($level, $message, $context);
    }
}

