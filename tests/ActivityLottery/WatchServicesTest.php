<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Bhp\Automation\Watch\LiveWatchService;
use Bhp\Automation\Watch\LiveWatchSession;
use Bhp\Automation\Watch\VideoWatchService;
use Bhp\Automation\Watch\VideoWatchSession;
use Tests\Support\Assert;

$video = VideoWatchSession::start('aid:1', 'cid:2');
Assert::same('aid:1', $video->archiveId);
Assert::same('cid:2', $video->cid);

$videoService = new VideoWatchService();
$videoStarted = $videoService->start('aid:9', 'cid:8');
Assert::same('aid:9', $videoStarted->archiveId);
Assert::same('cid:8', $videoStarted->cid);
Assert::true($videoService->finish($videoStarted, 30));
Assert::false($videoService->finish($videoStarted, 0));

$live = LiveWatchSession::start(12345);
Assert::same(12345, $live->roomId);

$liveService = new LiveWatchService();
$liveStarted = $liveService->start(12345);
Assert::same(12345, $liveStarted->roomId);
$liveNext = $liveService->heartbeat($liveStarted);
Assert::same(
    $liveStarted->seqId + 1,
    $liveNext->seqId,
    'heartbeat 后 seqId 应递增。'
);
