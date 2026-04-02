<?php

declare(strict_types=1);

ini_set('display_errors', '1');

require __DIR__ . '/Support/Assert.php';

/**
 * 获取与活动彩池相关的测试夹具路径
 */
function activityLotteryFixturePath(string $name): string
{
    return __DIR__ . '/Fixtures/activity_lottery/' . $name;
}
