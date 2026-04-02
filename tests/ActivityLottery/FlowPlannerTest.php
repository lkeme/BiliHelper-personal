<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$planner = new ActivityFlowPlanner();
$plan = $planner->plan(activityLotteryFixturePath('era-page-basic.html'));
Assert::true(
    method_exists($plan, 'stageSequence'),
    'FlowPlanner 返回的计划应包含 stageSequence。'
);
$stages = $plan->stageSequence();
Assert::true(
    in_array('window-detection', $stages, true),
    '计划应包含窗口识别阶段。'
);
Assert::true(
    in_array('catalog-merge', $stages, true),
    '计划应包含目录合并阶段。'
);
