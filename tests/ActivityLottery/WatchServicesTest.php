<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Bhp\Automation\Watch\LiveWatchService;
use Bhp\Automation\Watch\LiveWatchSession;
use Bhp\Automation\Watch\VideoWatchService;
use Bhp\Automation\Watch\VideoWatchSession;
use Bhp\Plugin\ActivityLottery\Internal\EraLiveWatchService;
use Bhp\Plugin\ActivityLottery\Internal\EraVideoWatchService;
use Tests\Support\Assert;

$video = VideoWatchSession::start('aid:1', 'cid:2');
Assert::same('aid:1', $video->archiveId);
Assert::same('cid:2', $video->cid);

$videoCalls = [];
$videoService = new VideoWatchService(
    videoAction: static function (string $aid, string $cid, string $bvid, array $options) use (&$videoCalls): array {
        $videoCalls[] = ['video', $aid, $cid, $bvid, $options];
        return ['code' => 0];
    },
    heartbeatAction: static function (string $aid, string $cid, int $progress, string $bvid, array $options) use (&$videoCalls): array {
        $videoCalls[] = ['heartbeat', $aid, $cid, $progress, $bvid, $options];
        return ['code' => 0];
    },
);
$videoStarted = $videoService->start('aid:9', 'cid:8', [
    'duration' => 120,
    'bvid' => 'BV1xx411c7mD',
    'session_id' => 'session-x',
]);
Assert::same('aid:9', $videoStarted->archiveId);
Assert::same('cid:8', $videoStarted->cid);
Assert::same(2, count($videoCalls), '视频 start 应发送 video + 首拍 heartbeat。');
Assert::same('video', $videoCalls[0][0]);
Assert::same('heartbeat', $videoCalls[1][0]);
Assert::true($videoService->finish($videoStarted, 130));
Assert::same(3, count($videoCalls));
Assert::same('heartbeat', $videoCalls[2][0]);
Assert::same(119, $videoCalls[2][5]['played_time'], '收尾 heartbeat 应使用 duration-1 作为 played_time。');
Assert::false($videoService->finish($videoStarted, 0));

$videoStartFail = new VideoWatchService(
    videoAction: static fn (...$args): array => ['code' => -1, 'message' => 'fail'],
);
$startFailed = false;
try {
    $videoStartFail->start('aid:1', 'cid:2', ['duration' => 10]);
} catch (\RuntimeException) {
    $startFailed = true;
}
Assert::true($startFailed, '视频 start 失败路径应抛异常。');

$live = LiveWatchSession::start(12345);
Assert::same(12345, $live->roomId);

$liveCalls = [];
$liveService = new LiveWatchService(
    roomEntryAction: static function (int $roomId) use (&$liveCalls): void {
        $liveCalls[] = ['roomEntryAction', $roomId];
    },
    enterAction: static function (
        int $roomId,
        int $ruid,
        int $parentAreaId,
        int $areaId,
        string $buvid,
        string $uuid,
        string $userAgent,
    ) use (&$liveCalls): array {
        $liveCalls[] = ['enter', $roomId, $ruid, $parentAreaId, $areaId, $buvid, $uuid, $userAgent];
        return [
            'code' => 0,
            'data' => [
                'secret_key' => 's1',
                'secret_rule' => [0, 1],
                'heartbeat_interval' => 75,
                'timestamp' => 1000,
            ],
        ];
    },
    heartbeatAction: static function (array $session, string $userAgent, int $interval) use (&$liveCalls): array {
        $liveCalls[] = ['heartbeat', $session, $userAgent, $interval];
        return [
            'code' => 0,
            'data' => [
                'secret_key' => 's2',
                'secret_rule' => [3, 4],
                'heartbeat_interval' => 90,
                'timestamp' => 2000,
            ],
        ];
    },
    userAgentResolver: static fn (): string => 'ua-test',
    buvidFactory: static fn (): string => 'buvid-test',
    uuidFactory: static fn (): string => 'uuid-test',
);
$liveStarted = $liveService->start(12345, [
    'ruid' => 1,
    'parent_area_id' => 2,
    'area_id' => 3,
]);
Assert::same(12345, $liveStarted->roomId);
Assert::same(75, $liveStarted->heartbeatInterval);
Assert::same('s1', $liveStarted->secretKey);
Assert::same(2, count($liveCalls), '直播 start 应触发 roomEntryAction + enter。');
$liveNext = $liveService->heartbeat($liveStarted);
Assert::same($liveStarted->seqId + 1, $liveNext->seqId);
Assert::same('s2', $liveNext->secretKey);
Assert::same([3, 4], $liveNext->secretRule);
Assert::same(90, $liveNext->heartbeatInterval);

$liveHeartbeatFail = new LiveWatchService(
    roomEntryAction: static function (...$args): void {
    },
    enterAction: static fn (...$args): array => [
        'code' => 0,
        'data' => [
            'secret_key' => 's1',
            'secret_rule' => [0],
            'heartbeat_interval' => 60,
            'timestamp' => 1000,
        ],
    ],
    heartbeatAction: static fn (...$args): array => ['code' => -1, 'message' => 'fail'],
    userAgentResolver: static fn (): string => 'ua',
    buvidFactory: static fn (): string => 'buvid',
    uuidFactory: static fn (): string => 'uuid',
);
$heartbeatFailed = false;
try {
    $session = $liveHeartbeatFail->start(1, ['ruid' => 1, 'parent_area_id' => 1, 'area_id' => 1]);
    $liveHeartbeatFail->heartbeat($session);
} catch (\RuntimeException) {
    $heartbeatFailed = true;
}
Assert::true($heartbeatFailed, '直播 heartbeat 失败路径应抛异常。');

$eraVideoCalls = [];
$eraVideoService = new EraVideoWatchService(new VideoWatchService(
    videoAction: static function (string $aid, string $cid, string $bvid, array $options) use (&$eraVideoCalls): array {
        $eraVideoCalls[] = ['video', $aid, $cid, $bvid, $options];
        return ['code' => 0];
    },
    heartbeatAction: static function (string $aid, string $cid, int $progress, string $bvid, array $options) use (&$eraVideoCalls): array {
        $eraVideoCalls[] = ['heartbeat', $aid, $cid, $progress, $bvid, $options];
        return ['code' => 0];
    },
));
Assert::true($eraVideoService->start(['aid' => '1', 'cid' => '2', 'duration' => 100], 'session-era'));
Assert::true($eraVideoService->finish(['aid' => '1', 'cid' => '2', 'duration' => 100], null, 10, 'session-era'));
Assert::same(3, count($eraVideoCalls), 'EraVideoWatchService 应委托公共 VideoWatchService。');

$eraLiveCalls = [];
$eraLiveShared = new LiveWatchService(
    roomEntryAction: static function (...$args): void {
    },
    enterAction: static function (...$args): array {
        return [
            'code' => 0,
            'data' => [
                'secret_key' => 'a',
                'secret_rule' => [1],
                'heartbeat_interval' => 60,
                'timestamp' => 111,
            ],
        ];
    },
    heartbeatAction: static function (array $session, string $userAgent, int $interval) use (&$eraLiveCalls): array {
        $eraLiveCalls[] = ['heartbeat', $session, $userAgent, $interval];
        return ['code' => 0, 'data' => ['timestamp' => 222]];
    },
    userAgentResolver: static fn (): string => 'ua',
    buvidFactory: static fn (): string => 'buvid',
    uuidFactory: static fn (): string => 'uuid',
);
$eraLiveService = new EraLiveWatchService($eraLiveShared);
$afterHeartbeat = $eraLiveService->heartbeat([
    'room_id' => 9,
    'ruid' => 1,
    'parent_area_id' => 2,
    'area_id' => 3,
    'seq_id' => 4,
    'heartbeat_interval' => 60,
    'ets' => 100,
    'secret_key' => 'old',
    'secret_rule' => [0],
    'last_heartbeat_at' => microtime(true) - 3,
]);
Assert::same(5, (int)$afterHeartbeat['seq_id']);
Assert::same(1, count($eraLiveCalls), 'EraLiveWatchService 的 heartbeat 应委托公共 LiveWatchService。');
