<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Bhp\Util\AsciiTable\AsciiTable;
use Tests\Support\Assert;

$singleColumnLines = AsciiTable::array2table([
    ['name' => 'Login'],
], '预加载插件列表');

Assert::same(false, str_contains($singleColumnLines[0] ?? '', '预加载插件列表'), '标题不应绘制在顶边框内。');
Assert::true(isset($singleColumnLines[1]), '标题应作为表格内部第一行存在。');
Assert::true(str_starts_with((string)($singleColumnLines[1] ?? ''), '│'), '标题行应位于表格内部。');
Assert::true(str_ends_with((string)($singleColumnLines[1] ?? ''), '│'), '标题行应位于表格内部。');
Assert::true(str_contains((string)($singleColumnLines[1] ?? ''), '预加载插件列表'), '标题行应包含完整标题。');
Assert::same(displayWidth($singleColumnLines[0] ?? ''), displayWidth($singleColumnLines[1] ?? ''), '窄表标题行宽度应与边框一致。');
Assert::same(displayWidth($singleColumnLines[0] ?? ''), displayWidth($singleColumnLines[2] ?? ''), '窄表标题分隔线宽度应与边框一致。');

$multiColumnLines = AsciiTable::array2table([
    [
        'name' => 'ActivityLottery',
        'desc' => '转盘活动',
        'priority' => '6666',
        'cycle' => '300',
        'start' => '06:00',
        'end' => '23:00',
        'enable' => '◉',
    ],
], '预加载插件列表');

Assert::same(false, str_contains($multiColumnLines[0] ?? '', '预加载插件列表'), '宽表标题不应绘制在顶边框内。');
Assert::true(str_contains((string)($multiColumnLines[1] ?? ''), '预加载插件列表'), '宽表标题行应包含完整标题。');
foreach ($multiColumnLines as $line) {
    Assert::same(displayWidth($multiColumnLines[0] ?? ''), displayWidth($line), '宽表每一行宽度都应一致。');
}

function displayWidth(string $line): int
{
    return mb_strwidth($line, 'UTF-8');
}
