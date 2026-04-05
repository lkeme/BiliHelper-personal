<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlowContext;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlowStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\ActivityLotteryGateway;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\NotifyDrawResultNodeRunner;
use PHPUnit\Framework\TestCase;

final class ActivityLotteryNotifyDrawResultNodeRunnerTest extends TestCase
{
    public function testRunPushesFormattedNoticeWithActivityAndRecordLinksForWinningResults(): void
    {
        $pushedChannel = '';
        $pushedMessage = '';
        $runner = new NotifyDrawResultNodeRunner(new ActivityLotteryGateway(
            noticePusher: static function (string $channel, string $message) use (&$pushedChannel, &$pushedMessage): void {
                $pushedChannel = $channel;
                $pushedMessage = $message;
            },
        ));

        $flow = $this->createFlow(
            [
                'title' => '战地风云6多人模式限时免费',
                'url' => 'https://www.bilibili.com/blackboard/era/test-activity.html',
                'activity_id' => '12345678',
            ],
            [
                'draw_summary' => [
                    'win_count' => 9,
                    'wins' => [
                        ['gift_name' => '60分钟双倍生涯经验卡'],
                        ['gift_name' => '60分钟双倍生涯经验卡'],
                        ['gift_name' => '60分钟双倍生涯经验卡'],
                        ['gift_name' => '60分钟双倍生涯经验卡'],
                        ['gift_name' => '60分钟双倍生涯经验卡'],
                        ['gift_name' => '60分钟双倍生涯经验卡'],
                        ['gift_name' => '60分钟双倍生涯经验卡'],
                        ['gift_name' => '60分钟双倍生涯经验卡'],
                        ['gift_name' => '稀有徽章'],
                    ],
                ],
            ],
        );
        $node = $flow->nodes()[0];

        $result = $runner->run($flow, $node, 1712332800);

        self::assertTrue($result->ok());
        self::assertSame('activity_lottery', $pushedChannel);
        self::assertSame(
            implode("\n", [
                '活动抽奖命中',
                '活动: 战地风云6多人模式限时免费',
                '命中次数: 9',
                '奖品清单:',
                '1. 60分钟双倍生涯经验卡 x8',
                '2. 稀有徽章 x1',
                '活动地址:',
                'https://www.bilibili.com/blackboard/era/test-activity.html',
                '中奖记录:',
                'https://www.bilibili.com/blackboard/era/new-award-record.html?activity_id=12345678',
            ]),
            $pushedMessage,
        );
        self::assertSame($pushedMessage, $result->payload()['context_patch']['notice_message'] ?? null);
    }

    public function testRunOmitsAwardRecordLinkWhenActivityIdIsMissing(): void
    {
        $pushedMessage = '';
        $runner = new NotifyDrawResultNodeRunner(new ActivityLotteryGateway(
            noticePusher: static function (string $channel, string $message) use (&$pushedMessage): void {
                $pushedMessage = $message;
            },
        ));

        $flow = $this->createFlow(
            [
                'title' => '测试活动',
                'url' => 'https://www.bilibili.com/blackboard/era/no-activity-id.html',
            ],
            [
                'draw_summary' => [
                    'win_count' => 1,
                    'wins' => [
                        ['gift_name' => '测试奖励'],
                    ],
                ],
            ],
        );
        $node = $flow->nodes()[0];

        $runner->run($flow, $node, 1712332800);

        self::assertStringContainsString('活动地址:', $pushedMessage);
        self::assertStringContainsString('https://www.bilibili.com/blackboard/era/no-activity-id.html', $pushedMessage);
        self::assertStringNotContainsString('中奖记录:', $pushedMessage);
        self::assertStringNotContainsString('new-award-record.html', $pushedMessage);
    }

    /**
     * @param array<string, mixed> $activity
     * @param array<string, mixed> $context
     */
    private function createFlow(array $activity, array $context): ActivityFlow
    {
        return new ActivityFlow(
            'notify-flow',
            '2026-04-06',
            $activity,
            ActivityFlowStatus::PENDING,
            0,
            [
                new ActivityNode(
                    'notify_draw_result',
                    [],
                    ActivityNodeStatus::PENDING,
                    [],
                ),
            ],
            0,
            0,
            new ActivityFlowContext($context),
            [],
            1712332800,
            1712332800,
        );
    }
}
