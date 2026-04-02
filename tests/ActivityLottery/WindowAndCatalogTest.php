<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Tests\Support\Assert;
use Bhp\Plugin\ActivityLottery\Internal\Runtime\ActivityLotteryWindow;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogLoader;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;

Assert::true(
    (new ActivityLotteryWindow('06:00:00', '23:00:00'))->contains(strtotime('2026-04-02 06:30:00')),
    '窗口应当覆盖早上 6:30。'
);
Assert::false(
    (new ActivityLotteryWindow('06:00:00', '23:00:00'))->contains(strtotime('2026-04-02 23:30:00')),
    '窗口不应当包含晚 23:30。'
);

// 目录加载与合并
$catalogLoader = new ActivityCatalogLoader(
    activityLotteryFixturePath('catalog.local.json'),
    activityLotteryFixturePath('catalog.remote.json')
);
$catalog = $catalogLoader->load();
Assert::same(
    3,
    count($catalog),
    '本地与远端所有条目应当合并后保留本地独有、远端独有以及共享条目。'
);
$sharedItem = null;
$localOnly = false;
$remoteOnly = false;
foreach ($catalog as $item) {
    if (!$item instanceof ActivityCatalogItem) {
        continue;
    }
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
    '共享活动的标题应用较新的远端版本。'
);
Assert::same(
    'shared-activity',
    $sharedItem->id(),
    '共享活动的唯一键应能定位。'
);
