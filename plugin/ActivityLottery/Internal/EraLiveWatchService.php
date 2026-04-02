<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal;

use Bhp\Api\XLive\WebInterface\V1\Second\ApiList;
use Bhp\Api\XLive\WebInterface\V1\WebMain\ApiRecommend;
use Bhp\Api\XLive\DataInterface\V1\X25Kn\ApiTrace;
use Bhp\Api\XLive\WebRoom\V1\Index\ApiIndex;
use Bhp\Log\Log;
use Bhp\Request\Request;
use Bhp\Runtime\Runtime;
use Bhp\Util\Fake\Fake;

final class EraLiveWatchService
{
    private bool $areaFetchFailureReported = false;
    /** @var array<string, string> */
    private array $areaWebIds = [];

    /**
     * @param string[] $roomIds
     * @return array<string, mixed>|null
     */
    public function start(array $roomIds, int $areaId = 0, int $parentAreaId = 0): ?array
    {
        $room = $this->pickLiveRoom($roomIds, $areaId, $parentAreaId);
        if ($room === null) {
            return null;
        }

        ApiIndex::roomEntryAction((int)$room['room_id']);

        $session = [
            'room_id' => (int)$room['room_id'],
            'ruid' => (int)$room['ruid'],
            'parent_area_id' => (int)$room['parent_area_id'],
            'area_id' => (int)$room['area_id'],
            'room_title' => (string)($room['room_title'] ?? ''),
            'room_uname' => (string)($room['room_uname'] ?? ''),
            'room_pick_source' => (string)($room['pick_source'] ?? 'room'),
            'seq_id' => 0,
            'live_buvid' => Fake::buvid(),
            'live_uuid' => Fake::uuid4(),
        ];

        $response = ApiTrace::enter(
            $session['room_id'],
            $session['ruid'],
            $session['parent_area_id'],
            $session['area_id'],
            $session['live_buvid'],
            $session['live_uuid'],
            $this->userAgent(),
        );
        if (($response['code'] ?? 0) !== 0) {
            throw new \RuntimeException("x25Kn/E失败 {$response['code']} -> {$response['message']}");
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $secretKey = trim((string)($data['secret_key'] ?? ''));
        $secretRule = $data['secret_rule'] ?? [];
        $heartbeatInterval = max(30, (int)($data['heartbeat_interval'] ?? 60));
        $ets = (int)($data['timestamp'] ?? 0);

        if ($secretKey === '' || !is_array($secretRule) || $ets <= 0) {
            throw new \RuntimeException('x25Kn/E返回缺少必要会话字段');
        }

        $session['heartbeat_interval'] = $heartbeatInterval;
        $session['ets'] = $ets;
        $session['secret_key'] = $secretKey;
        $session['secret_rule'] = array_values(array_map(static fn (mixed $rule): int => (int)$rule, $secretRule));
        $session['last_heartbeat_at'] = microtime(true);

        return $session;
    }

    /**
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    public function heartbeat(array $session): array
    {
        $now = microtime(true);
        $lastHeartbeatAt = max(0.0, (float)($session['last_heartbeat_at'] ?? 0.0));
        $elapsedSeconds = $lastHeartbeatAt > 0
            ? max(1, (int)floor($now - $lastHeartbeatAt))
            : max(1, (int)($session['heartbeat_interval'] ?? 60));

        $session['_debug_elapsed_seconds'] = $elapsedSeconds;
        $session['_debug_heartbeat_at'] = $now;

        $response = ApiTrace::heartbeat($session, $this->userAgent(), (int)($session['heartbeat_interval'] ?? 60));
        if (($response['code'] ?? 0) !== 0) {
            throw new \RuntimeException("x25Kn/X失败 {$response['code']} -> {$response['message']}");
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $session['seq_id'] = (int)($session['seq_id'] ?? 0) + 1;
        $session['heartbeat_interval'] = max(30, (int)($data['heartbeat_interval'] ?? $session['heartbeat_interval'] ?? 60));
        $session['ets'] = (int)($data['timestamp'] ?? $session['ets'] ?? 0);

        $nextSecretKey = trim((string)($data['secret_key'] ?? ''));
        if ($nextSecretKey !== '') {
            $session['secret_key'] = $nextSecretKey;
        }

        if (is_array($data['secret_rule'] ?? null) && $data['secret_rule'] !== []) {
            $session['secret_rule'] = array_values(array_map(static fn (mixed $rule): int => (int)$rule, $data['secret_rule']));
        }

        $session['last_heartbeat_at'] = $now;

        return $session;
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

            $response = ApiIndex::getInfoByRoom($roomId);
            if (($response['code'] ?? 0) !== 0) {
                continue;
            }

            $roomInfo = is_array($response['data']['room_info'] ?? null) ? $response['data']['room_info'] : [];
            if ((int)($roomInfo['live_status'] ?? 0) !== 1) {
                continue;
            }

            $actualRoomId = (int)($roomInfo['room_id'] ?? $roomId);
            $ruid = (int)($roomInfo['uid'] ?? 0);
            $roomParentAreaId = (int)($roomInfo['parent_area_id'] ?? 0);
            $roomAreaId = (int)($roomInfo['area_id'] ?? 0);
            if ($actualRoomId <= 0 || $ruid <= 0 || $roomParentAreaId <= 0 || $roomAreaId <= 0) {
                continue;
            }

            return [
                'room_id' => $actualRoomId,
                'ruid' => $ruid,
                'parent_area_id' => $roomParentAreaId,
                'area_id' => $roomAreaId,
                'room_title' => trim((string)($roomInfo['title'] ?? '')),
                'room_uname' => trim((string)($response['data']['anchor_info']['base_info']['uname'] ?? '')),
                'pick_source' => 'room',
            ];
        }

        if ($areaId <= 0) {
            return null;
        }

        $areaRoom = $this->pickAreaLiveRoom($areaId, $parentAreaId);
        if ($areaRoom !== null) {
            return $areaRoom;
        }

        return $this->pickRecommendedAreaLiveRoom($areaId, $parentAreaId);
    }

    /**
     * @return array<string, int|string>|null
     */
    private function pickAreaLiveRoom(int $areaId, int $parentAreaId): ?array
    {
        $parentAreaId = $parentAreaId > 0 ? $parentAreaId : $areaId;
        $webId = $this->resolveAreaWebId($areaId, $parentAreaId);
        $seedResponse = ApiList::getList($parentAreaId, $areaId, 1, '', $webId);
        $sortTypes = [''];

        if (($seedResponse['code'] ?? 0) === 0) {
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
            $maxPages = 2;
            for ($page = 1; $page <= $maxPages; $page++) {
                $response = $page === 1 && $sortType === ''
                    ? $seedResponse
                    : ApiList::getList($parentAreaId, $areaId, $page, $sortType, $webId);
                if (($response['code'] ?? 0) !== 0) {
                    if (!$this->areaFetchFailureReported) {
                        Log::warning(sprintf(
                            'ERA直播分区接口异常 area=%d parent=%d sort=%s page=%d code=%s message=%s',
                            $areaId,
                            $parentAreaId,
                            $sortType !== '' ? $sortType : '-',
                            $page,
                            (string)($response['code'] ?? ''),
                            (string)($response['message'] ?? $response['msg'] ?? '')
                        ));
                        $this->areaFetchFailureReported = true;
                    } else {
                        Log::debug(sprintf(
                            'ERA直播分区接口异常 area=%d parent=%d sort=%s page=%d code=%s message=%s',
                            $areaId,
                            $parentAreaId,
                            $sortType !== '' ? $sortType : '-',
                            $page,
                            (string)($response['code'] ?? ''),
                            (string)($response['message'] ?? $response['msg'] ?? '')
                        ));
                    }
                    continue;
                }

                $list = $response['data']['list'] ?? [];
                if (!is_array($list) || $list === []) {
                    Log::debug(sprintf(
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
                    if ($candidateRoomId <= 0) {
                        continue;
                    }

                    if ($candidateRuid <= 0) {
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
            $html = Request::get('other', $url);
            if (preg_match('/"access_id":"([^"]+)"/', $html, $matches) === 1) {
                return $this->areaWebIds[$cacheKey] = trim((string)$matches[1]);
            }
        } catch (\Throwable $throwable) {
            Log::debug(sprintf(
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
        $response = ApiRecommend::getMoreRecList();
        if (($response['code'] ?? 0) !== 0) {
            return null;
        }

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

        $response = ApiIndex::getInfoByRoom($roomId);
        if (($response['code'] ?? 0) !== 0) {
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

    private function userAgent(): string
    {
        return (string)Runtime::getInstance()->appContext()->device('platform.headers.pc_ua');
    }
}
