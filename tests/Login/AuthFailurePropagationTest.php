<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 2) . '/plugin/ActivityLottery/ActivityLottery.php';

use Bhp\Automation\Watch\LiveWatchService;
use Bhp\Automation\Watch\VideoWatchService;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowFactory;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\DrawGateway;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\EraTaskGateway;
use Bhp\Plugin\ActivityLottery\Internal\Node\EraFollowNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\EraShareNodeRunner;
use Bhp\Util\Exceptions\NoLoginException;
use Tests\Support\Assert;

$classifier = new AuthFailureClassifier();
$authFailureThrown = false;
try {
    $classifier->assertNotAuthFailure(['code' => -101, 'message' => '账号未登录']);
} catch (NoLoginException $exception) {
    $authFailureThrown = $exception->getMessage() === '账号未登录';
}
Assert::true($authFailureThrown, 'AuthFailureClassifier 应将 -101 归一为 NoLoginException。');

$csrfFailureThrown = false;
try {
    $classifier->assertNotAuthFailure(['code' => -111, 'message' => 'csrf 校验失败']);
} catch (NoLoginException $exception) {
    $csrfFailureThrown = str_contains($exception->getMessage(), 'csrf');
}
Assert::true($csrfFailureThrown, 'AuthFailureClassifier 应将 -111 归一为 NoLoginException。');

$drawGateway = new DrawGateway(
    static fn (array $payload): array => ['code' => -101, 'message' => '账号未登录'],
    static fn (array $payload): array => ['code' => 0, 'data' => [['gift_id' => 0, 'gift_name' => '未中奖']], 'message' => 'ok'],
);
$drawFailureThrown = false;
try {
    $drawGateway->refreshTimes([
        'lottery_id' => 'lottery-id',
        'url' => 'https://www.bilibili.com/blackboard/era/demo.html',
        'title' => 'demo',
    ]);
} catch (NoLoginException $exception) {
    $drawFailureThrown = $exception->getMessage() === '账号未登录';
}
Assert::true($drawFailureThrown, 'DrawGateway 应将登录失效归一为 NoLoginException。');

$taskGateway = new EraTaskGateway(
    static fn (string $taskId): array => ['code' => -111, 'message' => 'csrf 校验失败'],
    static fn (string $taskId, array $payload): array => ['code' => 0, 'data' => ['task_id' => $taskId], 'message' => 'ok'],
);
$taskFailureThrown = false;
try {
    $taskGateway->taskInfo('task-1');
} catch (NoLoginException $exception) {
    $taskFailureThrown = str_contains($exception->getMessage(), 'csrf');
}
Assert::true($taskFailureThrown, 'EraTaskGateway 应将 csrf 失效归一为 NoLoginException。');

$videoAuthFailureThrown = false;
try {
    (new VideoWatchService(
        videoAction: static fn (...$args): array => ['code' => -101, 'message' => '账号未登录'],
        heartbeatAction: static fn (...$args): array => ['code' => 0],
    ))->start('aid:1', 'cid:2', ['duration' => 60]);
} catch (NoLoginException $exception) {
    $videoAuthFailureThrown = $exception->getMessage() === '账号未登录';
}
Assert::true($videoAuthFailureThrown, 'VideoWatchService 应将登录失效归一为 NoLoginException。');

$liveAuthFailureThrown = false;
try {
    (new LiveWatchService(
        roomEntryAction: static function (...$args): void {
        },
        enterAction: static fn (...$args): array => ['code' => -101, 'message' => '账号未登录'],
        heartbeatAction: static fn (...$args): array => ['code' => 0, 'data' => []],
        userAgentResolver: static fn (): string => 'ua',
        buvidFactory: static fn (): string => 'buvid',
        uuidFactory: static fn (): string => 'uuid',
    ))->start(1, [
        'ruid' => 1,
        'parent_area_id' => 2,
        'area_id' => 3,
    ]);
} catch (NoLoginException $exception) {
    $liveAuthFailureThrown = $exception->getMessage() === '账号未登录';
}
Assert::true($liveAuthFailureThrown, 'LiveWatchService 应将登录失效归一为 NoLoginException。');

$shareFailureThrown = false;
try {
    (new EraShareNodeRunner(
        static fn (string $taskId, string $counter, string $url): array => ['code' => -101, 'message' => '账号未登录'],
    ))->run(
        buildActivityTaskFlow('era_task_share', 'task-share', [
            'task_name' => '分享活动',
            'capability' => 'share',
            'counter' => '1/1',
        ]),
        new ActivityNode('era_task_share', ['lane' => 'share', 'task_id' => 'task-share']),
        time(),
    );
} catch (NoLoginException $exception) {
    $shareFailureThrown = $exception->getMessage() === '账号未登录';
}
Assert::true($shareFailureThrown, 'EraShareNodeRunner 应将登录失效归一为 NoLoginException。');

$followFailureThrown = false;
try {
    (new EraFollowNodeRunner(
        static fn (int $uid): array => ['code' => -101, 'message' => '账号未登录'],
    ))->run(
        buildActivityTaskFlow('era_task_follow', 'task-follow', [
            'task_name' => '关注活动',
            'capability' => 'follow',
            'target_uids' => ['12345'],
        ]),
        new ActivityNode('era_task_follow', ['lane' => 'follow', 'task_id' => 'task-follow']),
        time(),
    );
} catch (NoLoginException $exception) {
    $followFailureThrown = $exception->getMessage() === '账号未登录';
}
Assert::true($followFailureThrown, 'EraFollowNodeRunner 应将登录失效归一为 NoLoginException。');

/**
 * @param array<string, mixed> $taskRow
 */
function buildActivityTaskFlow(string $nodeType, string $taskId, array $taskRow): ActivityFlow
{
    $flow = ActivityFlowFactory::create(
        ActivityCatalogItem::fromArray([
            'id' => 'login-auth-flow-' . $taskId,
            'activity_id' => 'login-auth-activity-' . $taskId,
            'lottery_id' => 'login-auth-lottery-' . $taskId,
            'title' => '登录归一化测试',
            'url' => 'https://www.bilibili.com/blackboard/era/login-auth.html',
        ]),
        '2026-04-04',
        [
            new ActivityNode($nodeType, ['lane' => 'task_status', 'task_id' => $taskId]),
        ],
    );

    $row = $flow->toArray();
    $row['context'] = [
        'era_page_snapshot' => [
            'activity_id' => 'login-auth-activity-' . $taskId,
            'page_id' => 'page-' . $taskId,
            'lottery_id' => 'login-auth-lottery-' . $taskId,
            'start_time' => 0,
            'end_time' => 0,
            'tasks' => [
                array_replace([
                    'task_id' => $taskId,
                    'task_name' => '任务',
                    'capability' => 'manual_only',
                    'support_level' => 'now',
                    'task_status' => 1,
                    'task_award_type' => 0,
                ], $taskRow),
            ],
        ],
    ];

    return ActivityFlow::fromArray($row);
}
