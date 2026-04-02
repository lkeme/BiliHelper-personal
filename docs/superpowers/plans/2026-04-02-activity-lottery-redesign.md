# ActivityLottery Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 用全新的活动任务流引擎替换当前 `ActivityLottery` 插件，实现每日乱序、节点级中恢复、严格顺序执行、保守节流与可复用观看组件。

**Architecture:** 新实现分为目录层、流模型层、池调度层、节点执行层与网关层。插件入口只驱动引擎；视频/直播观看能力上收为 `src/Automation/Watch/*` 公共组件；活动内部严格串行，活动之间通过虚拟池交错推进。

**Tech Stack:** PHP 8.3、现有 `Scheduler/Cache/Runtime/Request/Log/Notice` 基础设施、SQLite 缓存、纯 PHP 自定义测试脚本、现有 `tools/php-lint.php`。

---

## 实施原则

- 不在旧 `ActivityLottery.php` 上继续做局部修补。
- 先搭测试与领域模型，再切换运行入口。
- 观看视频/直播公共化，关注/分享保留在插件内部。
- 所有危险动作拆成可恢复的子步骤，不在节点内部 `sleep`。
- 旧实现只在最终切换时移除，避免中途失去对照与回退点。

## 计划前置说明

- 当前仓库没有现成的 `phpunit/phpstan` 基础设施，本计划先建立最小可执行测试脚本，全部通过 `php` 直接运行。
- 当前 `resources/activity_infos.json` 尚未提交到远端，运行时目录源先落地“本地源 + 远程源接口 + 远程默认关闭”，避免计划执行时卡在远程资源发布上。
- 由于当前会话未获得子代理授权，计划审阅按 reviewer 模板做本地人工审阅；如果后续你明确允许委派，可再补一次子代理审阅。

### Task 1: 建立最小测试与夹具基础

**Files:**
- Create: `tests/bootstrap.php`
- Create: `tests/Support/Assert.php`
- Create: `tests/Fixtures/activity_lottery/catalog.local.json`
- Create: `tests/Fixtures/activity_lottery/catalog.remote.json`
- Create: `tests/Fixtures/activity_lottery/era-page-basic.html`
- Create: `tools/run-activity-lottery-tests.php`
- Create: `tests/ActivityLottery/WindowAndCatalogTest.php`
- Create: `tests/ActivityLottery/FlowPlannerTest.php`

- [ ] **Step 1: 写窗口与目录合并的失败测试**

```php
<?php declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

use Tests\Support\Assert;
use Bhp\Plugin\ActivityLottery\Internal\Runtime\ActivityLotteryWindow;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogLoader;

Assert::true((new ActivityLotteryWindow('06:00:00', '23:00:00'))->contains(strtotime('2026-04-02 06:30:00')));
Assert::false((new ActivityLotteryWindow('06:00:00', '23:00:00'))->contains(strtotime('2026-04-02 23:30:00')));

$loader = new ActivityCatalogLoader(/* sources */);
$catalog = $loader->load();
Assert::same('remote-newer-title', $catalog[0]->title());
```

- [ ] **Step 2: 运行测试，确认当前失败**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/WindowAndCatalogTest.php`
Expected: FAIL，提示 `ActivityLotteryWindow` 或 `ActivityCatalogLoader` 不存在。

- [ ] **Step 3: 实现最小测试基建**

```php
// tests/Support/Assert.php
final class Assert
{
    public static function true(bool $value, string $message = 'expected true'): void { if (!$value) throw new RuntimeException($message); }
    public static function false(bool $value, string $message = 'expected false'): void { if ($value) throw new RuntimeException($message); }
    public static function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message !== '' ? $message : 'assert same failed');
        }
    }
}
```

```php
// tools/run-activity-lottery-tests.php
foreach (array_slice($argv, 1) as $file) {
    require $file;
}
fwrite(STDOUT, "activity-lottery-tests-ok\n");
```

- [ ] **Step 4: 让测试基建可单独跑通**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/WindowAndCatalogTest.php`
Expected: FAIL，但失败原因收敛到业务类缺失，而不是测试基建本身报错。

- [ ] **Step 5: 提交**

```bash
git add tests/bootstrap.php tests/Support/Assert.php tests/Fixtures/activity_lottery tools/run-activity-lottery-tests.php tests/ActivityLottery/WindowAndCatalogTest.php tests/ActivityLottery/FlowPlannerTest.php
git commit -m "test: add activity lottery test harness"
```

### Task 2: 抽出公共观看组件

**Files:**
- Create: `src/Automation/Watch/VideoWatchService.php`
- Create: `src/Automation/Watch/VideoWatchSession.php`
- Create: `src/Automation/Watch/LiveWatchService.php`
- Create: `src/Automation/Watch/LiveWatchSession.php`
- Create: `tests/ActivityLottery/WatchServicesTest.php`
- Modify: `plugin/ActivityLottery/Internal/EraVideoWatchService.php`
- Modify: `plugin/ActivityLottery/Internal/EraLiveWatchService.php`

- [ ] **Step 1: 写视频/直播公共服务的失败测试**

```php
<?php declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

use Tests\Support\Assert;
use Bhp\Automation\Watch\VideoWatchSession;
use Bhp\Automation\Watch\LiveWatchSession;

$video = VideoWatchSession::start('aid:1', 'cid:2');
Assert::same('aid:1', $video->archiveId);

$live = LiveWatchSession::start(12345);
Assert::same(12345, $live->roomId);
```

- [ ] **Step 2: 运行测试，确认失败**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/WatchServicesTest.php`
Expected: FAIL，提示 `Bhp\Automation\Watch\*` 类不存在。

- [ ] **Step 3: 实现最小公共会话对象与服务签名**

```php
final class VideoWatchSession
{
    public static function start(string $archiveId, string $cid): self { /* ... */ }
}

final class VideoWatchService
{
    public function start(string $archiveId, string $cid): VideoWatchSession { /* ... */ }
    public function finish(VideoWatchSession $session, int $watchedSeconds): bool { /* ... */ }
}
```

```php
final class LiveWatchService
{
    public function start(int $roomId): LiveWatchSession { /* ... */ }
    public function heartbeat(LiveWatchSession $session): LiveWatchSession { /* ... */ }
}
```

- [ ] **Step 4: 把旧观看实现改成薄适配层**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/WatchServicesTest.php`
Expected: PASS，然后执行 `php tools/php-lint.php src/Automation plugin/ActivityLottery/Internal`
Expected: `lint-ok`

- [ ] **Step 5: 提交**

```bash
git add src/Automation/Watch tests/ActivityLottery/WatchServicesTest.php plugin/ActivityLottery/Internal/EraVideoWatchService.php plugin/ActivityLottery/Internal/EraLiveWatchService.php
git commit -m "refactor: extract reusable watch services"
```

### Task 3: 建立目录层、运行窗口与公共支持规则

**Files:**
- Create: `plugin/ActivityLottery/Internal/Catalog/ActivityCatalogItem.php`
- Create: `plugin/ActivityLottery/Internal/Catalog/CatalogSourceInterface.php`
- Create: `plugin/ActivityLottery/Internal/Catalog/LocalCatalogSource.php`
- Create: `plugin/ActivityLottery/Internal/Catalog/RemoteCatalogSource.php`
- Create: `plugin/ActivityLottery/Internal/Catalog/ActivityCatalogLoader.php`
- Create: `plugin/ActivityLottery/Internal/Runtime/ActivityLotteryClock.php`
- Create: `plugin/ActivityLottery/Internal/Runtime/ActivityLotteryWindow.php`
- Create: `plugin/ActivityLottery/Internal/Support/ActivityCapability.php`
- Create: `plugin/ActivityLottery/Internal/Support/ActivityRandomizer.php`
- Create: `tests/ActivityLottery/WindowAndCatalogTest.php`

- [ ] **Step 1: 扩充目录与窗口测试，覆盖每日窗口、唯一键、按 `update_time` 合并**

```php
$loader = new ActivityCatalogLoader([$localSource, $remoteSource]);
$catalog = $loader->load();
Assert::same(1, count($catalog));
Assert::same('2026-04-02 18:00:00', $catalog[0]->updateTime());
Assert::same('follow', ActivityCapability::FOLLOW);
```

- [ ] **Step 2: 运行测试，确认失败**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/WindowAndCatalogTest.php`
Expected: FAIL，提示目录层类不存在或行为不匹配。

- [ ] **Step 3: 实现目录项、目录源接口、本地源与远程源默认关闭行为**

```php
interface CatalogSourceInterface
{
    /** @return ActivityCatalogItem[] */
    public function load(): array;
}

final class LocalCatalogSource implements CatalogSourceInterface
{
    public function __construct(private readonly string $path) {}
}
```

```php
final class RemoteCatalogSource implements CatalogSourceInterface
{
    public function __construct(private readonly bool $enabled, private readonly string $url) {}
}
```

- [ ] **Step 4: 实现目录合并器与窗口对象**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/WindowAndCatalogTest.php`
Expected: PASS，然后执行 `php tools/php-lint.php plugin/ActivityLottery/Internal/Catalog plugin/ActivityLottery/Internal/Runtime plugin/ActivityLottery/Internal/Support`
Expected: `lint-ok`

- [ ] **Step 5: 提交**

```bash
git add plugin/ActivityLottery/Internal/Catalog plugin/ActivityLottery/Internal/Runtime plugin/ActivityLottery/Internal/Support tests/ActivityLottery/WindowAndCatalogTest.php
git commit -m "feat: add activity lottery catalog and runtime primitives"
```

### Task 4: 建立流模型、节点模型与持久化存储

**Files:**
- Create: `plugin/ActivityLottery/Internal/Flow/ActivityFlowStatus.php`
- Create: `plugin/ActivityLottery/Internal/Flow/ActivityNodeStatus.php`
- Create: `plugin/ActivityLottery/Internal/Flow/ActivityNodeResult.php`
- Create: `plugin/ActivityLottery/Internal/Flow/ActivityNode.php`
- Create: `plugin/ActivityLottery/Internal/Flow/ActivityFlowContext.php`
- Create: `plugin/ActivityLottery/Internal/Flow/ActivityFlow.php`
- Create: `plugin/ActivityLottery/Internal/Flow/ActivityFlowFactory.php`
- Create: `plugin/ActivityLottery/Internal/Flow/ActivityFlowStore.php`
- Create: `tests/ActivityLottery/FlowStoreTest.php`

- [ ] **Step 1: 写流模型与中恢复的失败测试**

```php
$flow = ActivityFlowFactory::create($catalogItem, '2026-04-02', $nodes);
Assert::same('pending', $flow->status());

$store = new ActivityFlowStore('ActivityLottery');
$store->save([$flow]);
$loaded = $store->load('2026-04-02');
Assert::same($flow->id(), $loaded[0]->id());
```

- [ ] **Step 2: 运行测试，确认失败**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/FlowStoreTest.php`
Expected: FAIL，提示流模型类或 store 缺失。

- [ ] **Step 3: 实现最小流与节点对象**

```php
final class ActivityNode
{
    public function __construct(
        public readonly string $nodeId,
        public readonly string $type,
        public readonly string $status = ActivityNodeStatus::PENDING,
        public readonly int $nextRunAt = 0,
        public readonly int $attempts = 0,
        public readonly int $maxAttempts = 3,
        public readonly array $payload = [],
        public readonly array $result = [],
    ) {}
}
```

```php
final class ActivityFlowStore
{
    public function load(string $bizDate): array { /* 只返回当天流 */ }
    public function save(array $flows): void { /* 通过 Cache 显式 scope 持久化 */ }
    public function purgeBefore(string $bizDate): void { /* 清理旧流 */ }
}
```

- [ ] **Step 4: 跑通流模型测试并检查序列化一致性**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/FlowStoreTest.php`
Expected: PASS，然后执行 `php tools/php-lint.php plugin/ActivityLottery/Internal/Flow`
Expected: `lint-ok`

- [ ] **Step 5: 提交**

```bash
git add plugin/ActivityLottery/Internal/Flow tests/ActivityLottery/FlowStoreTest.php
git commit -m "feat: add activity flow domain and store"
```

### Task 5: 建立节点规划器、池预算与车道限速器

**Files:**
- Create: `plugin/ActivityLottery/Internal/Flow/ActivityFlowPlanner.php`
- Create: `plugin/ActivityLottery/Internal/Pool/ActivityFlowBudget.php`
- Create: `plugin/ActivityLottery/Internal/Pool/ActivityFlowPicker.php`
- Create: `plugin/ActivityLottery/Internal/Pool/ActivityLaneLimiter.php`
- Create: `plugin/ActivityLottery/Internal/Pool/ActivityFlowPool.php`
- Create: `tests/ActivityLottery/FlowPlannerTest.php`
- Create: `tests/ActivityLottery/FlowPoolTest.php`

- [ ] **Step 1: 写规划器与池调度失败测试**

```php
$planner = new ActivityFlowPlanner();
$flow = $planner->plan($catalogItem, $eraSnapshot, '2026-04-02');
Assert::same('load_activity_snapshot', $flow->nodes()[0]->type);
Assert::same('refresh_draw_times', $flow->nodes()[count($flow->nodes()) - 4]->type);

$pool = new ActivityFlowPool(new ActivityFlowBudget(4, 6, 3000), $picker, $laneLimiter);
$batch = $pool->pick($flows, time());
Assert::true(count($batch) <= 4);
```

- [ ] **Step 2: 运行测试，确认失败**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/FlowPlannerTest.php tests/ActivityLottery/FlowPoolTest.php`
Expected: FAIL，提示 planner/pool 类不存在。

- [ ] **Step 3: 实现规划器，生成固定骨架与动态 ERA 节点**

```php
final class ActivityFlowPlanner
{
    public function plan(ActivityCatalogItem $item, ?EraPageSnapshot $page, string $bizDate): ActivityFlow
    {
        // load -> validate -> parse -> dynamic ERA nodes -> refresh -> draw -> record -> notify -> finalize
    }
}
```

- [ ] **Step 4: 实现预算、挑选器与车道限速器**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/FlowPlannerTest.php tests/ActivityLottery/FlowPoolTest.php`
Expected: PASS，然后执行 `php tools/php-lint.php plugin/ActivityLottery/Internal/Pool plugin/ActivityLottery/Internal/Flow/ActivityFlowPlanner.php`
Expected: `lint-ok`

- [ ] **Step 5: 提交**

```bash
git add plugin/ActivityLottery/Internal/Flow/ActivityFlowPlanner.php plugin/ActivityLottery/Internal/Pool tests/ActivityLottery/FlowPlannerTest.php tests/ActivityLottery/FlowPoolTest.php
git commit -m "feat: add activity flow planner and pool controls"
```

### Task 6: 建立页面快照、网关层与准备/抽奖节点执行器

**Files:**
- Create: `plugin/ActivityLottery/Internal/Page/EraPageSnapshot.php`
- Create: `plugin/ActivityLottery/Internal/Page/EraTaskSnapshot.php`
- Create: `plugin/ActivityLottery/Internal/Page/EraTaskCapabilityResolver.php`
- Create: `plugin/ActivityLottery/Internal/Page/EraPageParser.php`
- Create: `plugin/ActivityLottery/Internal/Gateway/ActivityLotteryGateway.php`
- Create: `plugin/ActivityLottery/Internal/Gateway/EraTaskGateway.php`
- Create: `plugin/ActivityLottery/Internal/Gateway/DrawGateway.php`
- Create: `plugin/ActivityLottery/Internal/Node/NodeRunnerInterface.php`
- Create: `plugin/ActivityLottery/Internal/Node/LoadActivitySnapshotNodeRunner.php`
- Create: `plugin/ActivityLottery/Internal/Node/ValidateActivityNodeRunner.php`
- Create: `plugin/ActivityLottery/Internal/Node/ParseEraPageNodeRunner.php`
- Create: `plugin/ActivityLottery/Internal/Node/RefreshDrawTimesNodeRunner.php`
- Create: `plugin/ActivityLottery/Internal/Node/ExecuteDrawNodeRunner.php`
- Create: `plugin/ActivityLottery/Internal/Node/RecordDrawResultNodeRunner.php`
- Create: `plugin/ActivityLottery/Internal/Node/NotifyDrawResultNodeRunner.php`
- Create: `plugin/ActivityLottery/Internal/Node/FinalizeFlowNodeRunner.php`
- Create: `tests/ActivityLottery/PrepareAndDrawNodesTest.php`

- [ ] **Step 1: 写准备阶段与抽奖阶段节点 runner 的失败测试**

```php
$result = (new ValidateActivityNodeRunner(/* deps */))->run($flow, $node, $now);
Assert::same('succeeded', $result->status);

$refresh = (new RefreshDrawTimesNodeRunner(/* deps */))->run($flow, $node, $now);
Assert::same('waiting', $refresh->status);
Assert::same(3, $refresh->context['draw_times']);
```

- [ ] **Step 2: 运行测试，确认失败**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/PrepareAndDrawNodesTest.php`
Expected: FAIL，提示节点 runner 或网关层缺失。

- [ ] **Step 3: 实现页面快照与网关收口**

```php
final class DrawGateway
{
    public function refreshTimes(ActivityCatalogItem $item): array { /* ApiActivity::myTimes */ }
    public function drawOnce(ActivityCatalogItem $item): array { /* ApiActivity::doLottery */ }
}
```

```php
interface NodeRunnerInterface
{
    public function type(): string;
    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult;
}
```

- [ ] **Step 4: 实现准备与抽奖节点执行器**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/PrepareAndDrawNodesTest.php`
Expected: PASS，然后执行 `php tools/php-lint.php plugin/ActivityLottery/Internal/Page plugin/ActivityLottery/Internal/Gateway plugin/ActivityLottery/Internal/Node`
Expected: `lint-ok`

- [ ] **Step 5: 提交**

```bash
git add plugin/ActivityLottery/Internal/Page plugin/ActivityLottery/Internal/Gateway plugin/ActivityLottery/Internal/Node tests/ActivityLottery/PrepareAndDrawNodesTest.php
git commit -m "feat: add activity prepare and draw node runners"
```

### Task 7: 实现 ERA 节点执行器与等待态推进

**Files:**
- Create: `plugin/ActivityLottery/Internal/Node/EraFollowNodeRunner.php`
- Create: `plugin/ActivityLottery/Internal/Node/EraShareNodeRunner.php`
- Create: `plugin/ActivityLottery/Internal/Node/EraWatchVideoNodeRunner.php`
- Create: `plugin/ActivityLottery/Internal/Node/EraWatchLiveNodeRunner.php`
- Create: `plugin/ActivityLottery/Internal/Node/EraClaimRewardNodeRunner.php`
- Create: `plugin/ActivityLottery/Internal/Gateway/WatchVideoGateway.php`
- Create: `plugin/ActivityLottery/Internal/Gateway/WatchLiveGateway.php`
- Create: `tests/ActivityLottery/EraNodeRunnersTest.php`
- Modify: `src/Automation/Watch/VideoWatchService.php`
- Modify: `src/Automation/Watch/LiveWatchService.php`

- [ ] **Step 1: 写 ERA 任务节点的失败测试**

```php
$follow = (new EraFollowNodeRunner(/* deps */))->run($flow, $followNode, $now);
Assert::same('waiting', $follow->status);

$video = (new EraWatchVideoNodeRunner(/* deps */))->run($flow, $videoNode, $now);
Assert::same('waiting', $video->status);
Assert::true(isset($video->context['watch_video_session']));
```

- [ ] **Step 2: 运行测试，确认失败**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/EraNodeRunnersTest.php`
Expected: FAIL，提示 ERA 节点 runner 缺失。

- [ ] **Step 3: 实现关注、分享与领奖节点**

```php
final class EraFollowNodeRunner implements NodeRunnerInterface
{
    // 每次只推进一个目标 UID，然后返回 waiting
}
```

```php
final class EraClaimRewardNodeRunner implements NodeRunnerInterface
{
    // 人工场景 skipped，明确业务拒绝 failed，成功则 succeeded
}
```

- [ ] **Step 4: 实现视频/直播等待态节点**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/EraNodeRunnersTest.php`
Expected: PASS，然后执行 `php tools/php-lint.php plugin/ActivityLottery/Internal/Node src/Automation/Watch`
Expected: `lint-ok`

- [ ] **Step 5: 提交**

```bash
git add plugin/ActivityLottery/Internal/Node/Era* plugin/ActivityLottery/Internal/Gateway/Watch* src/Automation/Watch tests/ActivityLottery/EraNodeRunnersTest.php
git commit -m "feat: add ERA task node runners"
```

### Task 8: 重写插件入口并切换到新引擎

**Files:**
- Create: `plugin/ActivityLottery/Internal/Runtime/ActivityLotteryRuntime.php`
- Modify: `plugin/ActivityLottery/ActivityLottery.php`
- Modify: `plugin/ActivityLottery/Internal/bootstrap.php`
- Modify: `profile/example/config/user.ini`
- Create: `tests/ActivityLottery/PluginRuntimeTest.php`

- [ ] **Step 1: 写插件入口与每日边界行为的失败测试**

```php
$plugin = /* 构造 ActivityLottery with test doubles */;
$result = $plugin->runOnce();
Assert::true($result->nextRunAfterSeconds !== null);
Assert::same('2026-04-02', $runtime->bizDate());
```

- [ ] **Step 2: 运行测试，确认失败**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/PluginRuntimeTest.php`
Expected: FAIL，提示入口仍依赖旧实现或 runtime 缺失。

- [ ] **Step 3: 实现 `ActivityLotteryRuntime` 并重写插件入口**

```php
final class ActivityLotteryRuntime
{
    public function tick(): TaskResult
    {
        // 窗口检查 -> 清理旧流 -> 载入目录 -> 建流 -> pool 推进一步 -> 返回调度结果
    }
}
```

```php
final class ActivityLottery extends BasePlugin implements PluginTaskInterface
{
    public function runOnce(): TaskResult
    {
        if (!$this->enabled('activity_lottery')) {
            return TaskResult::keepSchedule();
        }
        return $this->runtime()->tick();
    }
}
```

```ini
[activity_lottery]
enable = true
window_start = "06:00:00"
window_end = "23:00:00"
max_flows_per_tick = 4
max_steps_per_tick = 6
max_runtime_ms_per_tick = 3000
remote_catalog_enable = false
remote_catalog_url = ""
```

- [ ] **Step 4: 跑通插件入口测试与语法检查**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/PluginRuntimeTest.php`
Expected: PASS  
Run: `php tools/php-lint.php plugin/ActivityLottery src/Automation tests`
Expected: `lint-ok`

- [ ] **Step 5: 提交**

```bash
git add plugin/ActivityLottery/ActivityLottery.php plugin/ActivityLottery/Internal/Runtime plugin/ActivityLottery/Internal/bootstrap.php profile/example/config/user.ini tests/ActivityLottery/PluginRuntimeTest.php
git commit -m "refactor: switch activity lottery to flow engine"
```

### Task 9: 清理旧实现、补充回归验证并更新相关说明

**Files:**
- Delete: `plugin/ActivityLottery/Concerns/ActivityLotteryCampaignDrawFlow.php`
- Delete: `plugin/ActivityLottery/Internal/ActivityCampaign.php`
- Delete: `plugin/ActivityLottery/Internal/EraActivityFollowTarget.php`
- Delete: `plugin/ActivityLottery/Internal/EraActivityPage.php`
- Delete: `plugin/ActivityLottery/Internal/EraActivityPageParser.php`
- Delete: `plugin/ActivityLottery/Internal/EraActivityTask.php`
- Delete: `plugin/ActivityLottery/Internal/EraLiveWatchService.php`
- Delete: `plugin/ActivityLottery/Internal/EraTopicArchiveService.php`
- Delete: `plugin/ActivityLottery/Internal/EraVideoWatchService.php`
- Modify: `docs/ARCHITECTURE.md`
- Modify: `docs/PLUGIN_GUIDE.md`
- Modify: `docs/CHANGELOG.md`

- [ ] **Step 1: 写回归清单并确认旧入口已不再引用旧文件**

Run: `rg -n "ActivityLotteryCampaignDrawFlow|EraActivityPage|EraActivityTask|EraVideoWatchService|EraLiveWatchService" plugin src docs`
Expected: 仅剩新设计文档中的历史说明或不再有生产代码引用。

- [ ] **Step 2: 删除旧实现文件**

Run: `php tools/php-lint.php plugin/ActivityLottery src/Automation tests`
Expected: `lint-ok`

- [ ] **Step 3: 运行完整活动插件测试集**

Run: `php tools/run-activity-lottery-tests.php tests/ActivityLottery/WindowAndCatalogTest.php tests/ActivityLottery/WatchServicesTest.php tests/ActivityLottery/FlowStoreTest.php tests/ActivityLottery/FlowPlannerTest.php tests/ActivityLottery/FlowPoolTest.php tests/ActivityLottery/PrepareAndDrawNodesTest.php tests/ActivityLottery/EraNodeRunnersTest.php tests/ActivityLottery/PluginRuntimeTest.php`
Expected: `activity-lottery-tests-ok`

- [ ] **Step 4: 更新架构与插件说明**

```markdown
- `ActivityLottery` 已重构为活动流引擎
- `watch_video` / `watch_live` 已提取到 `src/Automation/Watch/*`
- 每日边界固定为 `06:00:00~23:00:00`
```

- [ ] **Step 5: 提交**

```bash
git add plugin/ActivityLottery src/Automation docs/ARCHITECTURE.md docs/PLUGIN_GUIDE.md docs/CHANGELOG.md
git commit -m "docs: finalize activity lottery redesign"
```

## 最终验证清单

- [ ] `php tools/run-activity-lottery-tests.php tests/ActivityLottery/WindowAndCatalogTest.php tests/ActivityLottery/WatchServicesTest.php tests/ActivityLottery/FlowStoreTest.php tests/ActivityLottery/FlowPlannerTest.php tests/ActivityLottery/FlowPoolTest.php tests/ActivityLottery/PrepareAndDrawNodesTest.php tests/ActivityLottery/EraNodeRunnersTest.php tests/ActivityLottery/PluginRuntimeTest.php` 可一次性跑通
- [ ] `php tools/php-lint.php plugin/ActivityLottery src/Automation tests` 返回 `lint-ok`
- [ ] `php app.php m:d -p ActivityLottery --reset-cache` 不再出现“`myTimes` 可用但 `addTimes` 误判活动失效”的旧路径
- [ ] 插件在 `06:00:00` 前与 `23:00:00` 后不会推进当天流
- [ ] 跨天后旧 `biz_date` 流会被废弃
- [ ] 视频/直播节点在进程重启后能以中恢复方式重新进入节点
- [ ] 同一天活动流启动顺序是乱序的
- [ ] 抽奖执行按单次保守频率推进，不在一个节点里连续猛抽
