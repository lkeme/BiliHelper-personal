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
Assert::same(
    'remote-newer-title',
    $catalog['items'][0]['title'] ?? null,
    '远端目录中新标题应覆盖本地的旧标题。'
);
