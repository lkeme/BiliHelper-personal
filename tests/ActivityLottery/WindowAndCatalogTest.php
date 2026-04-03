<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogLoader;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\LocalCatalogSource;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\RemoteCatalogSource;
use Bhp\Plugin\ActivityLottery\Internal\Runtime\ActivityLotteryClock;
use Bhp\Plugin\ActivityLottery\Internal\Runtime\ActivityLotteryWindow;
use Bhp\Plugin\ActivityLottery\Internal\Support\ActivityCapability;
use Tests\Support\Assert;

Assert::true(
    (new ActivityLotteryWindow('06:00:00', '23:00:00'))->contains(strtotime('2026-04-02 06:00:00')),
    '窗口应包含开始边界 06:00:00。'
);
Assert::false(
    (new ActivityLotteryWindow('06:00:00', '23:00:00'))->contains(strtotime('2026-04-02 23:00:00')),
    '窗口不应包含结束边界 23:00:00。'
);
Assert::true(
    (new ActivityLotteryWindow('06:00:00', '23:00:00'))->contains(strtotime('2026-04-02 22:59:59')),
    '窗口应覆盖 22:59:59。'
);

$clock = new ActivityLotteryClock(static fn (): int => strtotime('2026-04-02 06:30:00'));
Assert::true(
    (new ActivityLotteryWindow('06:00:00', '23:00:00'))->contains($clock->now()),
    '窗口判定应可配合 ActivityLotteryClock 的当前时间。'
);

$byActivityId = ActivityCatalogItem::fromArray([
    'id' => 'fallback',
    'activity_id' => 'act-id',
    'page_id' => 'page-id',
    'lottery_id' => 'lot-id',
    'url' => 'https://example.test/1',
    'title' => 'title-1',
    'update_time' => '2026-04-01T12:00:00+00:00',
]);
Assert::same('act-id', $byActivityId->id(), '唯一键应优先使用 activity_id。');

$byPageId = ActivityCatalogItem::fromArray([
    'id' => 'fallback',
    'page_id' => 'page-id',
    'lottery_id' => 'lot-id',
    'url' => 'https://example.test/2',
    'title' => 'title-2',
    'update_time' => '2026-04-01T12:00:00+00:00',
]);
Assert::same('page-id', $byPageId->id(), '唯一键在 activity_id 缺失时应使用 page_id。');

$byLotteryId = ActivityCatalogItem::fromArray([
    'id' => 'fallback',
    'lottery_id' => 'lot-id',
    'url' => 'https://example.test/3',
    'title' => 'title-3',
    'update_time' => '2026-04-01T12:00:00+00:00',
]);
Assert::same('lot-id', $byLotteryId->id(), '唯一键在 page_id 缺失时应使用 lottery_id。');

$byUrl = ActivityCatalogItem::fromArray([
    'id' => 'fallback',
    'url' => 'https://example.test/4',
    'title' => 'title-4',
    'update_time' => '2026-04-01T12:00:00+00:00',
]);
Assert::same('https://example.test/4', $byUrl->id(), '唯一键在 lottery_id 缺失时应使用 url。');

Assert::true(ActivityCapability::FOLLOW !== '', 'ActivityCapability::FOLLOW 常量应可用。');
Assert::true(ActivityCapability::LIKE !== '', 'ActivityCapability::LIKE 常量应可用。');
Assert::true(ActivityCapability::COIN !== '', 'ActivityCapability::COIN 常量应可用。');
Assert::true(ActivityCapability::SHARE !== '', 'ActivityCapability::SHARE 常量应可用。');

/** @var ActivityCatalogItem[] $catalog */
$catalogLoader = new ActivityCatalogLoader([
    new LocalCatalogSource(activityLotteryFixturePath('catalog.local.json')),
    new RemoteCatalogSource(activityLotteryFixturePath('catalog.remote.json')),
]);
$catalog = $catalogLoader->load();
Assert::same(
    2,
    count($catalog),
    '远端默认关闭时，目录应只包含本地条目。'
);

/** @var ActivityCatalogItem[] $catalog */
$catalogLoader = new ActivityCatalogLoader([
    new LocalCatalogSource(activityLotteryFixturePath('catalog.local.json')),
    new RemoteCatalogSource(activityLotteryFixturePath('catalog.remote.json'), true),
]);
$catalog = $catalogLoader->load();
Assert::true(is_array($catalog), '目录加载结果应为数组。');
Assert::same(
    3,
    count($catalog),
    '启用远端后，应合并本地独有、远端独有和共享条目。'
);
$sharedItem = null;
$localOnly = false;
$remoteOnly = false;
foreach ($catalog as $item) {
    Assert::true(
        $item instanceof ActivityCatalogItem,
        '目录加载结果应由 ActivityCatalogItem 组成。'
    );
    if ($item->id() === 'shared-activity') {
        $sharedItem = $item;
    }
    if ($item->id() === 'local-only') {
        $localOnly = true;
    }
    if ($item->id() === 'remote-only') {
        $remoteOnly = true;
    }
}
Assert::true($localOnly, '合并结果中应包含本地独有活动。');
Assert::true($remoteOnly, '合并结果中应包含远端独有活动。');
Assert::true($sharedItem !== null, '共享活动应当可定位。');
Assert::same(
    'remote-newer-title',
    $sharedItem->title(),
    '共享活动应按 update_time 采用较新的远端版本。'
);
Assert::same(
    'shared-activity',
    $sharedItem->id(),
    '共享活动的唯一键应可稳定定位。'
);
