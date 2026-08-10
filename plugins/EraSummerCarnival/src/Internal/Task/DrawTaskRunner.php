<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task;

use Bhp\Api\Api\X\Activity\ApiActivity;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\State\CarnivalStateStore;

/**
 * 抽奖（R6）。
 *
 * 抽奖次数为 0 时不发起 do 请求（避免空转）。
 * 单 tick 只抽一次，仍有次数则返回 again() 让编排层短延迟继续，避免长时间占用事件循环。
 *
 * 响应结构（取自 ActivityLottery/ExecuteDrawNodeRunner）：
 *   myTimes   → data.times
 *   doLottery → data[] 每项 { gift_id, gift_name, award_sid?, award_info? }，未中奖时 gift_name 含「未中奖」
 */
final class DrawTaskRunner implements TaskRunnerInterface
{
    private const NEXT_DRAW_DELAY_SECONDS = 8.0;

    private readonly ApiActivity $apiActivity;
    private readonly CarnivalStateStore $stateStore;
    private readonly AuthFailureClassifier $authFailureClassifier;

    /** @var \Closure(string, string, array<string, mixed>): void */
    private readonly \Closure $logger;

    /** @var \Closure(string, string): void */
    private readonly \Closure $notifier;

    /**
     * @param callable(string, string, array<string, mixed>): void $logger
     * @param callable(string, string): void $notifier
     */
    public function __construct(
        ApiActivity $apiActivity,
        CarnivalStateStore $stateStore,
        callable $logger,
        callable $notifier,
        ?AuthFailureClassifier $authFailureClassifier = null,
    ) {
        $this->apiActivity = $apiActivity;
        $this->stateStore = $stateStore;
        $this->logger = \Closure::fromCallable($logger);
        $this->notifier = \Closure::fromCallable($notifier);
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    public function key(): string
    {
        return 'draw';
    }

    public function run(CarnivalContext $context): CarnivalStepResult
    {
        $info = $context->snapshot->lotteryInfo();
        if (trim((string)($info['sid'] ?? '')) === '') {
            return CarnivalStepResult::skipped('抽奖: 缺少 lottery_id');
        }

        $timesResponse = $this->apiActivity->myTimes($info);
        $this->authFailureClassifier->assertNotAuthFailure($timesResponse, '次元奇旅查询抽奖次数时账号未登录');

        $code = (int)($timesResponse['code'] ?? -1);
        if ($code !== 0) {
            $message = trim((string)($timesResponse['message'] ?? $timesResponse['msg'] ?? ''));
            $this->log('warning', '次元奇旅: 查询抽奖次数失败', ['code' => $code, 'message' => $message]);

            return CarnivalStepResult::failed(900.0, "查询抽奖次数失败 {$code} -> {$message}");
        }

        $times = max(0, (int)($timesResponse['data']['times'] ?? 0));
        if ($times <= 0) {
            return CarnivalStepResult::skipped('抽奖: 当前无可用次数');
        }

        $drawResponse = $this->apiActivity->doLottery($info, 1);
        $this->authFailureClassifier->assertNotAuthFailure($drawResponse, '次元奇旅抽奖时账号未登录');

        $drawCode = (int)($drawResponse['code'] ?? -1);
        $drawMessage = trim((string)($drawResponse['message'] ?? $drawResponse['msg'] ?? ''));
        if ($drawCode !== 0) {
            $this->log('warning', '次元奇旅: 抽奖失败', ['code' => $drawCode, 'message' => $drawMessage]);

            return CarnivalStepResult::failed(900.0, "抽奖失败 {$drawCode} -> {$drawMessage}");
        }

        $total = $this->stateStore->addDrawCount($context->bizDate, 1);
        $wins = $this->extractWins($drawResponse);
        $remaining = max(0, $times - 1);

        if ($wins === []) {
            $this->log('info', '次元奇旅: 抽奖未中奖', [
                '今日已抽' => $total,
                '剩余次数' => $remaining,
            ]);
        } else {
            foreach ($wins as $giftName) {
                $this->log('notice', '次元奇旅: 抽奖中奖', ['奖品' => $giftName]);
                ($this->notifier)('lottery', "次元奇旅: 抽奖中奖 -> {$giftName}");
            }
        }

        return $remaining > 0
            ? CarnivalStepResult::again(self::NEXT_DRAW_DELAY_SECONDS, "抽奖完成，剩余 {$remaining} 次")
            : CarnivalStepResult::done('抽奖完成，次数已用尽');
    }

    /**
     * 提取中奖奖品名（过滤未中奖项）
     *
     * @param array<string, mixed> $response
     * @return string[]
     */
    private function extractWins(array $response): array
    {
        $items = $response['data'] ?? null;
        if (!is_array($items)) {
            return [];
        }

        $wins = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $giftName = trim((string)($item['gift_name'] ?? ''));
            if ($giftName === '' || str_contains($giftName, '未中奖')) {
                continue;
            }

            $giftId = (int)($item['gift_id'] ?? 0);
            $awardSid = trim((string)($item['award_sid'] ?? ''));
            if ($giftId <= 0 && $awardSid === '' && !is_array($item['award_info'] ?? null)) {
                continue;
            }

            $wins[] = $giftName;
        }

        return array_values(array_unique($wins));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        ($this->logger)($level, $message, $context);
    }
}
