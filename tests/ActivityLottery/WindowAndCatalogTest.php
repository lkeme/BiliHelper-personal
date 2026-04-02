<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

// 窗口时间判断
$window = new ActivityLotteryWindow('06:00:00', '23:00:00');
Assert::true(
    $window->contains(strtotime('2026-04-02 06:30:00')),
    '窗口应当覆盖早上 6:30。'
);
Assert::false(
    $window->contains(strtotime('2026-04-02 23:30:00')),
    '窗口不应当包含晚 23:30。'
);

// 目录加载与合并
$catalogLoader = new ActivityCatalogLoader(
    activityLotteryFixturePath('catalog.local.json'),
    activityLotteryFixturePath('catalog.remote.json')
);
$catalog = $catalogLoader->load();
$items = $catalog['items'] ?? [];
Assert::same(
    3,
    count($items),
    '本地与远端所有条目应当合并后保留本地独有、远端独有以及共享条目。'
);
$ids = array_column($items, 'id');
Assert::true(in_array('shared-activity', $ids, true), '合并结果中应包含共享活动。');
Assert::true(in_array('local-only', $ids, true), '合并结果中应包含本地独有活动。');
Assert::true(in_array('remote-only', $ids, true), '合并结果中应包含远端独有活动。');

$sharedItem = null;
foreach ($items as $item) {
    if (($item['id'] ?? null) === 'shared-activity') {
        $sharedItem = $item;
        break;
    }
}
Assert::true($sharedItem !== null, '共享活动应当可定位。');
Assert::same(
    'remote-newer-title',
    $sharedItem['title'] ?? null,
    '共享活动的标题应用较新的远端版本。'
);
