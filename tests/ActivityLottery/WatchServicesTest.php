<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Bhp\Automation\Watch\LiveWatchService;
use Bhp\Automation\Watch\LiveWatchSession;
use Bhp\Automation\Watch\VideoWatchService;
use Bhp\Automation\Watch\VideoWatchSession;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\WatchLiveGateway;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\WatchVideoGateway;
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
$videoStartFailMessage = '';
try {
    $videoStartFail->start('aid:1', 'cid:2', ['duration' => 10]);
} catch (\RuntimeException $exception) {
    $videoStartFailMessage = $exception->getMessage();
}
Assert::same('视频观看初始化失败 -1 -> fail', $videoStartFailMessage);

$videoFirstHeartbeatFail = new VideoWatchService(
    videoAction: static fn (...$args): array => ['code' => 0],
    heartbeatAction: static fn (...$args): array => ['code' => -2, 'message' => 'hb fail'],
);
$videoHeartbeatFailMessage = '';
try {
    $videoFirstHeartbeatFail->start('aid:1', 'cid:2', ['duration' => 10]);
} catch (\RuntimeException $exception) {
    $videoHeartbeatFailMessage = $exception->getMessage();
}
Assert::same('视频观看首拍心跳失败 -2 -> hb fail', $videoHeartbeatFailMessage);

$videoInvalidContextFailed = false;
try {
    $videoService->start('aid:1', 'cid:2', ['duration' => 0]);
} catch (\RuntimeException) {
    $videoInvalidContextFailed = true;
}
Assert::true($videoInvalidContextFailed, '视频 start 在 duration 非法时应快速失败。');

$videoFinishCalls = [];
$videoFinishService = new VideoWatchService(
    heartbeatAction: static function (string $aid, string $cid, int $progress, string $bvid, array $options) use (&$videoFinishCalls): array {
        $videoFinishCalls[] = [$aid, $cid, $progress, $bvid, $options];
        return ['code' => 0];
    },
);
Assert::false(
    $videoFinishService->finish(VideoWatchSession::start('', 'cid:2', ['duration' => 100]), 10),
    'finish 在 archiveId 非法时应快速失败。',
);
Assert::false(
    $videoFinishService->finish(VideoWatchSession::start('aid:1', '', ['duration' => 100]), 10),
    'finish 在 cid 非法时应快速失败。',
);
Assert::false(
    $videoFinishService->finish(VideoWatchSession::start('aid:1', 'cid:2', ['duration' => 0]), 10),
    'finish 在 duration 非法时应快速失败。',
);
Assert::same(0, count($videoFinishCalls), 'finish 非法 session 不应触发 heartbeat action。');

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

$videoGatewayCalls = [];
$videoGateway = new WatchVideoGateway(
    static fn (array $archive): ?array => $archive === [] ? null : $archive,
    static fn (string $topicId, int $limit = 20): array => [
        ['aid' => '123', 'bvid' => 'BV1topicx123', 'title' => 'topic archive'],
    ],
    static function (array $archive, string $sessionId) use (&$videoGatewayCalls): bool {
        $videoGatewayCalls[] = ['start', $archive, $sessionId];
        return true;
    },
    static function (array $archive, int $watchedSeconds, string $sessionId) use (&$videoGatewayCalls): bool {
        $videoGatewayCalls[] = ['finish', $archive, $watchedSeconds, $sessionId];
        return true;
    },
);
Assert::same(['aid' => '1', 'cid' => '2', 'duration' => 100], $videoGateway->normalizeArchive(['aid' => '1', 'cid' => '2', 'duration' => 100]));
Assert::true($videoGateway->start(['aid' => '1', 'cid' => '2', 'duration' => 100], 'gateway-session'));
Assert::true($videoGateway->finish(['aid' => '1', 'cid' => '2', 'duration' => 100], 20, 'gateway-session'));
Assert::same(2, count($videoGatewayCalls), 'WatchVideoGateway 应代理 start/finish。');
Assert::same(1, count($videoGateway->fetchTopicArchives('topic-1')), 'WatchVideoGateway 应暴露 topic archive 获取能力。');

$liveGatewayCalls = [];
$liveGateway = new WatchLiveGateway(
    static function (array $roomIds, int $areaId = 0, int $parentAreaId = 0) use (&$liveGatewayCalls): ?array {
        $liveGatewayCalls[] = ['start', $roomIds, $areaId, $parentAreaId];
        return [
            'room_id' => 9,
            'ruid' => 123,
            'parent_area_id' => 456,
            'area_id' => 789,
            'heartbeat_interval' => 60,
        ];
    },
    static function (array $session) use (&$liveGatewayCalls): array {
        $liveGatewayCalls[] = ['heartbeat', $session];
        return array_replace($session, ['seq_id' => (int)($session['seq_id'] ?? 0) + 1]);
    },
);
$gatewaySession = $liveGateway->start(['9'], 789, 456);
Assert::same(9, (int)($gatewaySession['room_id'] ?? 0));
$afterGatewayHeartbeat = $liveGateway->heartbeat([
    'room_id' => 9,
    'ruid' => 1,
    'parent_area_id' => 2,
    'area_id' => 3,
    'seq_id' => 4,
    'heartbeat_interval' => 60,
]);
Assert::same(5, (int)($afterGatewayHeartbeat['seq_id'] ?? 0));
Assert::same(2, count($liveGatewayCalls), 'WatchLiveGateway 应代理 start/heartbeat。');

$liveStartCalls = [];
$liveStartBoundaryService = new LiveWatchService(
    roomEntryAction: static function (int $roomId) use (&$liveStartCalls): void {
        $liveStartCalls[] = ['roomEntryAction', $roomId];
    },
    enterAction: static function (...$args) use (&$liveStartCalls): array {
        $liveStartCalls[] = ['enter', ...$args];
        return ['code' => 0, 'data' => []];
    },
    userAgentResolver: static fn (): string => 'ua-test',
    buvidFactory: static fn (): string => 'buvid-test',
    uuidFactory: static fn (): string => 'uuid-test',
);
$liveStartBoundaryFailed = false;
try {
    $liveStartBoundaryService->start(0, [
        'ruid' => 0,
        'parent_area_id' => 2,
        'area_id' => 3,
    ]);
} catch (\RuntimeException) {
    $liveStartBoundaryFailed = true;
}
Assert::true($liveStartBoundaryFailed, 'start 在会话边界非法时应快速失败。');
Assert::same(0, count($liveStartCalls), 'start 边界校验失败时不应触发外部 action。');

$liveStartIdentityCalls = [];
$liveStartIdentityBoundaryService = new LiveWatchService(
    roomEntryAction: static function (int $roomId) use (&$liveStartIdentityCalls): void {
        $liveStartIdentityCalls[] = ['roomEntryAction', $roomId];
    },
    enterAction: static function (...$args) use (&$liveStartIdentityCalls): array {
        $liveStartIdentityCalls[] = ['enter', ...$args];
        return ['code' => 0, 'data' => []];
    },
    userAgentResolver: static fn (): string => 'ua-test',
);
$liveStartIdentityBoundaryFailed = false;
try {
    $liveStartIdentityBoundaryService->start(1, [
        'ruid' => 1,
        'parent_area_id' => 2,
        'area_id' => 3,
        'live_buvid' => '',
        'live_uuid' => '',
    ]);
} catch (\RuntimeException) {
    $liveStartIdentityBoundaryFailed = true;
}
Assert::true($liveStartIdentityBoundaryFailed, 'start 在 live_buvid/live_uuid 非法时应快速失败。');
Assert::same(0, count($liveStartIdentityCalls), 'start 身份边界校验失败时不应触发外部 action。');

$liveStartMissingSecretService = new LiveWatchService(
    roomEntryAction: static function (...$args): void {
    },
    enterAction: static fn (...$args): array => [
        'code' => 0,
        'data' => [
            'secret_key' => 'k1',
            'heartbeat_interval' => 60,
            'timestamp' => 1234,
        ],
    ],
    userAgentResolver: static fn (): string => 'ua-test',
    buvidFactory: static fn (): string => 'buvid-test',
    uuidFactory: static fn (): string => 'uuid-test',
);
$liveStartMissingSecretFailed = false;
try {
    $liveStartMissingSecretService->start(1, [
        'ruid' => 1,
        'parent_area_id' => 2,
        'area_id' => 3,
    ]);
} catch (\RuntimeException) {
    $liveStartMissingSecretFailed = true;
}
Assert::true($liveStartMissingSecretFailed, 'start 在 code=0 但缺少 secret_rule 时应快速失败。');

$liveHeartbeatBoundaryCalls = [];
$liveHeartbeatBoundaryService = new LiveWatchService(
    heartbeatAction: static function (...$args) use (&$liveHeartbeatBoundaryCalls): array {
        $liveHeartbeatBoundaryCalls[] = $args;
        return ['code' => 0, 'data' => []];
    },
    userAgentResolver: static fn (): string => 'ua-test',
);
$liveHeartbeatBoundaryFailed = false;
try {
    $liveHeartbeatBoundaryService->heartbeat(LiveWatchSession::start(1, [
        'ruid' => 1,
        'parent_area_id' => 2,
        'area_id' => 3,
        'heartbeat_interval' => 60,
        'ets' => 0,
        'secret_key' => '',
        'secret_rule' => [],
        'live_buvid' => '',
        'live_uuid' => '',
    ]));
} catch (\RuntimeException) {
    $liveHeartbeatBoundaryFailed = true;
}
Assert::true($liveHeartbeatBoundaryFailed, 'heartbeat 在会话边界非法时应快速失败。');
Assert::same(0, count($liveHeartbeatBoundaryCalls), 'heartbeat 边界校验失败时不应触发外部 action。');

$liveHeartbeatRoomBoundaryCalls = [];
$liveHeartbeatRoomBoundaryService = new LiveWatchService(
    heartbeatAction: static function (...$args) use (&$liveHeartbeatRoomBoundaryCalls): array {
        $liveHeartbeatRoomBoundaryCalls[] = $args;
        return ['code' => 0, 'data' => []];
    },
    userAgentResolver: static fn (): string => 'ua-test',
);
$liveHeartbeatRoomBoundaryFailed = false;
try {
    $liveHeartbeatRoomBoundaryService->heartbeat(LiveWatchSession::start(0, [
        'ruid' => 1,
        'parent_area_id' => 2,
        'area_id' => 3,
        'heartbeat_interval' => 60,
        'ets' => 100,
        'secret_key' => 's1',
        'secret_rule' => [0],
        'live_buvid' => 'buvid-x',
        'live_uuid' => 'uuid-x',
    ]));
} catch (\RuntimeException) {
    $liveHeartbeatRoomBoundaryFailed = true;
}
Assert::true($liveHeartbeatRoomBoundaryFailed, 'heartbeat 在 room_id/ruid/area 非法时应快速失败。');
Assert::same(0, count($liveHeartbeatRoomBoundaryCalls), 'heartbeat 房间边界校验失败时不应触发外部 action。');
