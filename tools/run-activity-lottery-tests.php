#!/usr/bin/env php
<?php

declare(strict_types=1);

$tests = array_slice($argv, 1);

if (!$tests) {
    fwrite(STDERR, '请至少提供一个测试文件路径。' . PHP_EOL);
    exit(1);
}

foreach ($tests as $testFile) {
    $resolved = realpath($testFile);
    if ($resolved === false || !is_file($resolved)) {
        fwrite(STDERR, "无法找到测试文件：{$testFile}" . PHP_EOL);
        exit(1);
    }

    require $resolved;
}

echo 'activity-lottery-tests-ok' . PHP_EOL;
