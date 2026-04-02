<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$planner = new ActivityLotteryFlowPlanner(activityLotteryFixturePath('era-page-basic.html'));
Assert::true(
    $planner->hasWindow('06:00:00', '23:00:00'),
    'FlowPlanner 应当识别页面中的默认窗口。'
);
