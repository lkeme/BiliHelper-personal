<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Runtime;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlowStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraWatchProgress;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\ResolvedActivityView;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\ResolvedEraTaskView;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Page\EraTaskSnapshot;

final class ActivityLotteryLifecycleLogger
{
    /** @var array<string, array{fingerprint: string, logged_at: int}> */
    private array $waitingLogState = [];
    public function buildNodeExecuteLog(ActivityFlow $flow, \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $node): array
    {
        $context = $this->buildNodeBusinessContext($flow, $node);
        $activityTitle = $context['activity_title'] ?? '未命名活动';
        $taskName = trim((string)($context['task_name'] ?? ''));
        $label = $taskName !== '' ? sprintf('任务「%s」', $taskName) : sprintf('节点「%s」', $this->nodeLabel($node->type()));
        $suffix = $this->buildNodeExecuteSuffix($context, $node->type());
        $progressSuffix = $this->buildInlineProgressSuffix($context);

        return [
            sprintf('活动「%s」开始执行%s%s%s', $activityTitle, $label, $suffix, $progressSuffix),
            $context,
        ];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function buildNodeResultLog(
        ActivityFlow $beforeFlow,
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $beforeNode,
        ActivityFlow $afterFlow,
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
    ): array {
        $context = $this->buildNodeBusinessContext($beforeFlow, $beforeNode, $afterFlow);
        $context['node_status_label'] = $this->nodeStatusLabel($afterNode->status());
        $context['flow_status_label'] = $this->flowStatusLabel($afterFlow->status());
        $activityTitle = $context['activity_title'] ?? '未命名活动';
        $taskName = trim((string)($context['task_name'] ?? ''));
        $label = $taskName !== '' ? sprintf('任务「%s」', $taskName) : sprintf('节点「%s」', $this->nodeLabel($beforeNode->type()));
        $message = $this->buildDetailedNodeResultMessage($beforeNode->type(), $afterNode, $context);

        return [
            sprintf('活动「%s」%s结果: %s', $activityTitle, $label, $message),
            $context,
        ];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function buildFlowSummaryLog(
        ActivityFlow $beforeFlow,
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $beforeNode,
        ActivityFlow $afterFlow,
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
    ): array {
        $context = $this->buildNodeBusinessContext($beforeFlow, $beforeNode, $afterFlow);
        $context['node_status_label'] = $this->nodeStatusLabel($afterNode->status());
        $context['flow_status_label'] = $this->flowStatusLabel($afterFlow->status());
        $activityTitle = $context['activity_title'] ?? '未命名活动';
        $summary = $this->buildFlowSummaryMessage($beforeNode->type(), $afterNode, $context);
        if ($summary === '') {
            return ['', $context];
        }

        [$stage, $detail] = $this->splitStageSummary($summary);
        $segments = array_values(array_filter([
            $this->buildFlowProgressPrefix($context),
            $stage !== '' ? sprintf('当前阶段：%s', $stage) : '',
            ($context['node_status_label'] ?? '') !== '' ? sprintf('状态：%s', (string)$context['node_status_label']) : '',
            $detail,
        ], static fn (string $value): bool => $value !== ''));

        return [
            sprintf('活动「%s」%s', $activityTitle, implode('，', $segments)),
            $context,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildNodeBusinessContext(
        ActivityFlow $flow,
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $node,
        ?ActivityFlow $afterFlow = null,
    ): array {
        $activity = ResolvedActivityView::fromFlow($flow)->toActivityArray();
        $context = [
            'activity_title' => trim((string)($activity['title'] ?? '')),
            'activity_id' => trim((string)($activity['activity_id'] ?? '')),
        ];
        $stateSource = $afterFlow ?? $flow;
        $totalNodes = count($stateSource->nodes());
        $context['node_position'] = $totalNodes > 0 ? min($flow->currentNodeIndex() + 1, $totalNodes) : 0;
        $context['node_total'] = $totalNodes;
        $stateContext = $stateSource->context()->toArray();
        $context['wait_delay_seconds'] = $this->resolveWaitDelaySeconds($stateSource);
        $context['draw_times_remaining'] = max(0, (int)($stateContext['draw_times_remaining'] ?? 0));
        $context['draw_batch_size'] = max(0, (int)($stateContext['draw_batch_size'] ?? 0));
        $context['draw_batch_win_count'] = max(0, (int)($stateContext['draw_batch_win_count'] ?? 0));
        $context['draw_batch_win_names'] = array_values(array_filter(array_map(
            static fn (mixed $name): string => trim((string)$name),
            is_array($stateContext['draw_batch_win_names'] ?? null) ? $stateContext['draw_batch_win_names'] : [],
        )));
        $lastDraw = $stateContext['last_draw_result'] ?? null;
        if (is_array($lastDraw)) {
            $context['last_draw_gift_name'] = trim((string)($lastDraw['gift_name'] ?? ''));
            $context['last_draw_gift_id'] = (int)($lastDraw['gift_id'] ?? 0);
        }
        $drawSummary = $stateContext['draw_summary'] ?? null;
        if (is_array($drawSummary)) {
            $context['draw_total_count'] = max(0, (int)($drawSummary['total_count'] ?? 0));
            $context['draw_win_count'] = max(0, (int)($drawSummary['win_count'] ?? 0));
            $context['draw_win_names'] = array_values(array_filter(array_map(
                static fn (mixed $win): string => is_array($win) ? trim((string)($win['gift_name'] ?? '')) : '',
                is_array($drawSummary['wins'] ?? null) ? $drawSummary['wins'] : [],
            )));
        }

        $taskId = trim((string)($node->payload()['task_id'] ?? ''));
        if ($taskId === '') {
            return $context;
        }

        $context['task_id'] = $taskId;
        $taskView = ResolvedEraTaskView::fromFlowAndNode($flow, $node);
        $task = $taskView->task();
        if ($task !== null) {
            $context['task_name'] = $task->taskName();
            $context['display_target_seconds'] = $this->resolveDisplayTargetSeconds($task);
        }

        $runtimeMap = $stateContext['era_task_runtime'] ?? [];
        $state = is_array($runtimeMap[$taskId] ?? null) ? $runtimeMap[$taskId] : [];
        $context['local_watch_seconds'] = max(0, (int)($state['local_watch_seconds'] ?? 0));

        $archive = $this->resolveCurrentArchive($task, $state);
        if (is_array($archive)) {
            $context['archive_aid'] = trim((string)($archive['aid'] ?? ''));
            $context['archive_bvid'] = trim((string)($archive['bvid'] ?? ''));
        }
        if (is_array($state['live_session'] ?? null)) {
            $liveSession = $state['live_session'];
            $context['room_id'] = (int)($liveSession['room_id'] ?? 0);
            $context['heartbeat_interval'] = max(0, (int)($liveSession['heartbeat_interval'] ?? 0));
        }
        if ($task !== null) {
            $targetUids = $task->targetUids();
            $completedCount = min(count($targetUids), max(0, (int)($state['follow_target_index'] ?? 0)));
            $context['follow_total_count'] = count($targetUids);
            $context['follow_completed_count'] = $completedCount;
            if (isset($targetUids[$completedCount])) {
                $context['target_uid'] = (string)$targetUids[$completedCount];
            }
        }
        return $context;
    }

    private function nodeLabel(string $nodeType): string
    {
        return match ($nodeType) {
            'load_activity_snapshot' => '加载活动页',
            'validate_activity_window' => '校验活动窗口',
            'parse_era_page' => '解析活动任务页',
            'refresh_draw_times' => '刷新抽奖次数',
            'execute_draw' => '执行抽奖',
            'record_draw_result' => '记录抽奖结果',
            'notify_draw_result' => '通知抽奖结果',
            'final_claim_reward' => '收尾领奖',
            'finalize_flow' => '收尾活动流',
            'era_task_follow' => '关注任务',
            'era_task_share' => '分享任务',
            'era_task_watch_video_fixed', 'era_task_watch_video_topic' => '观看视频任务',
            'era_task_watch_live' => '观看直播任务',
            'era_task_unfollow' => '取消关注任务',
            'era_task_claim_reward' => '领奖任务',
            'era_task_skipped' => '跳过任务',
            default => $nodeType,
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildNodeExecuteSuffix(array $context, string $nodeType): string
    {
        return match ($nodeType) {
            'era_task_follow' => ($context['target_uid'] ?? '') !== ''
                ? sprintf(' [目标UID=%s]', (string)$context['target_uid'])
                : '',
            'era_task_watch_video_fixed', 'era_task_watch_video_topic' => ($archiveLabel = $this->archiveLabel($context)) !== ''
                ? sprintf(' [稿件=%s]', $archiveLabel)
                : '',
            'era_task_watch_live' => (int)($context['room_id'] ?? 0) > 0
                ? sprintf(' [房间=%d]', (int)$context['room_id'])
                : '',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildInlineProgressSuffix(array $context): string
    {
        $position = max(0, (int)($context['node_position'] ?? 0));
        $total = max(0, (int)($context['node_total'] ?? 0));
        if ($position <= 0 || $total <= 0) {
            return '';
        }

        return sprintf('（进度 %d/%d）', $position, $total);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildFlowProgressPrefix(array $context): string
    {
        $position = max(0, (int)($context['node_position'] ?? 0));
        $total = max(0, (int)($context['node_total'] ?? 0));
        if ($position <= 0 || $total <= 0) {
            return '';
        }

        return sprintf('流程进度 %d/%d', $position, $total);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildDetailedNodeResultMessage(
        string $nodeType,
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        $fallback = trim((string)($afterNode->result()?->message() ?? '执行结束'));
        $delay = $this->formatDelaySuffix((int)($context['wait_delay_seconds'] ?? 0));

        return match ($nodeType) {
            'era_task_follow' => $this->buildFollowResultMessage($afterNode, $context, $fallback, $delay),
            'era_task_watch_video_fixed', 'era_task_watch_video_topic' => $this->buildWatchVideoResultMessage($afterNode, $context, $fallback, $delay),
            'era_task_watch_live' => $this->buildWatchLiveResultMessage($afterNode, $context, $fallback, $delay),
            'refresh_draw_times' => $this->buildRefreshDrawResultMessage($afterNode, $context, $fallback),
            'execute_draw' => $this->buildExecuteDrawResultMessage($afterNode, $context, $fallback, $delay),
            'record_draw_result' => $this->buildRecordDrawResultMessage($afterNode, $context, $fallback),
            default => $fallback,
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildFlowSummaryMessage(
        string $nodeType,
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        return match ($nodeType) {
            'era_task_follow' => $this->buildFollowSummaryMessage($afterNode, $context),
            'era_task_watch_video_fixed', 'era_task_watch_video_topic' => $this->buildWatchVideoSummaryMessage($afterNode, $context),
            'era_task_watch_live' => $this->buildWatchLiveSummaryMessage($afterNode, $context),
            'refresh_draw_times' => $this->buildRefreshDrawSummaryMessage($afterNode, $context),
            'execute_draw' => $this->buildExecuteDrawSummaryMessage($afterNode, $context),
            'record_draw_result' => $this->buildRecordDrawSummaryMessage($afterNode, $context),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    public function resolveNodeResultLogLevel(
        string $nodeType,
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        if ($afterNode->status() === ActivityNodeStatus::FAILED || $this->looksLikeFailureMessage((string)($context['node_message'] ?? ''))) {
            return 'warning';
        }

        if ($nodeType === 'record_draw_result' && max(0, (int)($context['draw_win_count'] ?? 0)) > 0) {
            return 'notice';
        }

        return 'info';
    }

    /**
     * @param array<string, mixed> $context
     */
    public function resolveFlowSummaryLogLevel(
        string $nodeType,
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        if ($afterNode->status() === ActivityNodeStatus::FAILED || $this->looksLikeFailureMessage((string)($context['node_message'] ?? ''))) {
            return 'warning';
        }

        if ($nodeType === 'record_draw_result' && max(0, (int)($context['draw_win_count'] ?? 0)) > 0) {
            return 'notice';
        }

        return 'info';
    }

    private function looksLikeFailureMessage(string $message): bool
    {
        $message = trim($message);
        if ($message === '') {
            return false;
        }

        foreach (['失败', '异常', 'error', 'failed'] as $keyword) {
            if (str_contains(strtolower($message), strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitStageSummary(string $summary): array
    {
        $summary = trim($summary);
        if ($summary === '') {
            return ['', ''];
        }

        $chunks = explode('，', $summary, 2);
        $stage = trim((string)($chunks[0] ?? ''));
        $detail = trim((string)($chunks[1] ?? ''));

        return [$stage, $detail];
    }

    private function nodeStatusLabel(string $status): string
    {
        return match ($status) {
            ActivityNodeStatus::WAITING => '等待继续',
            ActivityNodeStatus::SUCCEEDED => '已完成',
            ActivityNodeStatus::SKIPPED => '已跳过',
            ActivityNodeStatus::FAILED => '执行失败',
            default => '执行中',
        };
    }

    private function flowStatusLabel(string $status): string
    {
        return match ($status) {
            ActivityFlowStatus::BLOCKED => '阻塞中',
            ActivityFlowStatus::COMPLETED => '已完成',
            ActivityFlowStatus::FAILED => '已失败',
            ActivityFlowStatus::RUNNING => '执行中',
            default => '待执行',
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildFollowResultMessage(
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
        string $fallback,
        string $delay,
    ): string {
        $completed = max(0, (int)($context['follow_completed_count'] ?? 0));
        $total = max(0, (int)($context['follow_total_count'] ?? 0));
        $nextTargetUid = trim((string)($context['target_uid'] ?? ''));

        if ($afterNode->status() === ActivityNodeStatus::WAITING && $total > 0) {
            $suffix = $nextTargetUid !== '' ? sprintf('，下一目标 UID=%s', $nextTargetUid) : '';
            return sprintf('已完成 %d/%d%s%s', $completed, $total, $suffix, $delay);
        }

        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED && $total > 0) {
            return sprintf('%s，已完成 %d/%d', $fallback, $completed, $total);
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildWatchVideoResultMessage(
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
        string $fallback,
        string $delay,
    ): string {
        $currentSeconds = max(0, (int)($context['local_watch_seconds'] ?? 0));
        $targetSeconds = max(0, (int)($context['display_target_seconds'] ?? 0));
        $progress = $targetSeconds > 0
            ? sprintf('%d/%d 秒', $currentSeconds, $targetSeconds)
            : sprintf('%d 秒', $currentSeconds);
        $archiveLabel = $this->archiveLabel($context);
        $archivePrefix = $archiveLabel !== '' ? sprintf('稿件 %s，', $archiveLabel) : '';

        if ($afterNode->status() === ActivityNodeStatus::WAITING) {
            if (str_contains($fallback, '视频观看继续推进')) {
                return sprintf('恢复推进，%s当前累计 %s%s', $archivePrefix, $progress, $delay);
            }
            if (str_contains($fallback, '视频观看已启动')) {
                if ($currentSeconds > 0) {
                    return sprintf('重新建链，%s当前累计 %s%s', $archivePrefix, $progress, $delay);
                }

                return sprintf('首次启动观看，%s当前累计 %s%s', $archivePrefix, $progress, $delay);
            }
            if ($archivePrefix === '' && $currentSeconds <= 0) {
                return $fallback . $delay;
            }
            return sprintf('%s当前累计 %s%s', $archivePrefix, $progress, $delay);
        }

        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('%s，累计 %s', $fallback, $progress);
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildWatchLiveResultMessage(
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
        string $fallback,
        string $delay,
    ): string {
        $roomId = max(0, (int)($context['room_id'] ?? 0));
        $currentSeconds = max(0, (int)($context['local_watch_seconds'] ?? 0));
        $targetSeconds = max(0, (int)($context['display_target_seconds'] ?? 0));
        $progress = $targetSeconds > 0
            ? sprintf('%d/%d 秒', $currentSeconds, $targetSeconds)
            : sprintf('%d 秒', $currentSeconds);
        $roomPrefix = $roomId > 0 ? sprintf('房间 %d，', $roomId) : '';

        if ($afterNode->status() === ActivityNodeStatus::WAITING) {
            if (str_contains($fallback, '直播观看继续推进')) {
                return sprintf('恢复推进，%s当前累计 %s%s', $roomPrefix, $progress, $delay);
            }
            if (str_contains($fallback, '直播观看已启动')) {
                if ($currentSeconds > 0) {
                    return sprintf('重新接入直播间，%s当前累计 %s%s', $roomPrefix, $progress, $delay);
                }

                return sprintf('首次接入直播间，%s当前累计 %s%s', $roomPrefix, $progress, $delay);
            }
            if ($roomPrefix === '' && $currentSeconds <= 0) {
                return $fallback . $delay;
            }
            return sprintf('%s当前累计 %s%s', $roomPrefix, $progress, $delay);
        }

        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('%s，累计 %s', $fallback, $progress);
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildRefreshDrawResultMessage(
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
        string $fallback,
    ): string {
        $remaining = max(0, (int)($context['draw_times_remaining'] ?? 0));
        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('当前可抽 %d 次', $remaining);
        }
        if ($afterNode->status() === ActivityNodeStatus::SKIPPED) {
            return '当前无可用抽奖次数';
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildExecuteDrawResultMessage(
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
        string $fallback,
        string $delay,
    ): string {
        $remaining = max(0, (int)($context['draw_times_remaining'] ?? 0));
        $batchSize = max(0, (int)($context['draw_batch_size'] ?? 0));
        $batchWinCount = max(0, (int)($context['draw_batch_win_count'] ?? 0));
        $batchWinNames = is_array($context['draw_batch_win_names'] ?? null) ? $context['draw_batch_win_names'] : [];
        $resultName = trim((string)($context['last_draw_gift_name'] ?? ''));
        $resultLabel = $resultName !== '' ? $resultName : '结果缺少奖品名';

        if ($batchSize > 1) {
            $batchSummary = $batchWinCount > 0
                ? sprintf('本次连抽 %d 次，命中 %d 次，奖品：%s', $batchSize, $batchWinCount, implode(' / ', $batchWinNames))
                : sprintf('本次连抽 %d 次，未命中', $batchSize);
            if ($afterNode->status() === ActivityNodeStatus::WAITING) {
                return sprintf('%s，剩余 %d 次%s', $batchSummary, $remaining, $delay);
            }
            if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
                return sprintf('%s，剩余 %d 次', $batchSummary, $remaining);
            }
        }

        if ($afterNode->status() === ActivityNodeStatus::WAITING) {
            return sprintf('本次结果：%s，剩余 %d 次%s', $resultLabel, $remaining, $delay);
        }
        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('本次结果：%s，剩余 %d 次', $resultLabel, $remaining);
        }
        if ($afterNode->status() === ActivityNodeStatus::SKIPPED) {
            return '抽奖次数已耗尽';
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildRecordDrawResultMessage(
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
        string $fallback,
    ): string {
        if ($afterNode->status() !== ActivityNodeStatus::SUCCEEDED) {
            return $fallback;
        }

        $total = max(0, (int)($context['draw_total_count'] ?? 0));
        $winCount = max(0, (int)($context['draw_win_count'] ?? 0));
        $wins = is_array($context['draw_win_names'] ?? null) ? $context['draw_win_names'] : [];
        if ($winCount <= 0) {
            return sprintf('累计抽奖 %d 次，未命中', $total);
        }

        return sprintf('累计抽奖 %d 次，命中 %d 次，奖品：%s', $total, $winCount, implode(' / ', $wins));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildFollowSummaryMessage(
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        $completed = max(0, (int)($context['follow_completed_count'] ?? 0));
        $total = max(0, (int)($context['follow_total_count'] ?? 0));
        if ($afterNode->status() === ActivityNodeStatus::WAITING && $total > 0) {
            $nextTargetUid = trim((string)($context['target_uid'] ?? ''));
            $suffix = $nextTargetUid !== '' ? sprintf('，下一目标 UID=%s', $nextTargetUid) : '';
            return sprintf('关注任务，已完成 %d/%d%s%s', $completed, $total, $suffix, $this->formatDelaySuffix((int)($context['wait_delay_seconds'] ?? 0)));
        }

        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED && $total > 0) {
            return sprintf('关注任务，已完成 %d/%d', $completed, $total);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildWatchVideoSummaryMessage(
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        $currentSeconds = max(0, (int)($context['local_watch_seconds'] ?? 0));
        $targetSeconds = max(0, (int)($context['display_target_seconds'] ?? 0));
        $progress = $targetSeconds > 0 ? sprintf('%d/%d 秒', $currentSeconds, $targetSeconds) : sprintf('%d 秒', $currentSeconds);
        $archiveLabel = $this->archiveLabel($context);
        $prefix = $archiveLabel !== '' ? sprintf('观看视频，稿件 %s，', $archiveLabel) : '观看视频，';

        if ($afterNode->status() === ActivityNodeStatus::WAITING) {
            return sprintf('%s当前累计 %s%s', $prefix, $progress, $this->formatDelaySuffix((int)($context['wait_delay_seconds'] ?? 0)));
        }
        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('%s累计 %s', $prefix, $progress);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildWatchLiveSummaryMessage(
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        $currentSeconds = max(0, (int)($context['local_watch_seconds'] ?? 0));
        $targetSeconds = max(0, (int)($context['display_target_seconds'] ?? 0));
        $progress = $targetSeconds > 0 ? sprintf('%d/%d 秒', $currentSeconds, $targetSeconds) : sprintf('%d 秒', $currentSeconds);
        $roomId = max(0, (int)($context['room_id'] ?? 0));
        $prefix = $roomId > 0 ? sprintf('观看直播，房间 %d，', $roomId) : '观看直播，';

        if ($afterNode->status() === ActivityNodeStatus::WAITING) {
            return sprintf('%s当前累计 %s%s', $prefix, $progress, $this->formatDelaySuffix((int)($context['wait_delay_seconds'] ?? 0)));
        }
        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('%s累计 %s', $prefix, $progress);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildRefreshDrawSummaryMessage(
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        $remaining = max(0, (int)($context['draw_times_remaining'] ?? 0));
        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('抽奖阶段，当前可抽 %d 次', $remaining);
        }
        if ($afterNode->status() === ActivityNodeStatus::SKIPPED) {
            return '抽奖阶段，当前无可用抽奖次数';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildExecuteDrawSummaryMessage(
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        $remaining = max(0, (int)($context['draw_times_remaining'] ?? 0));
        $batchSize = max(0, (int)($context['draw_batch_size'] ?? 0));
        $batchWinCount = max(0, (int)($context['draw_batch_win_count'] ?? 0));
        $batchWinNames = is_array($context['draw_batch_win_names'] ?? null) ? $context['draw_batch_win_names'] : [];
        $resultName = trim((string)($context['last_draw_gift_name'] ?? ''));
        $resultLabel = $resultName !== '' ? $resultName : '结果缺少奖品名';
        if ($batchSize > 1) {
            $batchSummary = $batchWinCount > 0
                ? sprintf('抽奖阶段，本次连抽 %d 次，命中 %d 次，奖品：%s', $batchSize, $batchWinCount, implode(' / ', $batchWinNames))
                : sprintf('抽奖阶段，本次连抽 %d 次，未命中', $batchSize);
            if ($afterNode->status() === ActivityNodeStatus::WAITING) {
                return sprintf('%s，剩余 %d 次%s', $batchSummary, $remaining, $this->formatDelaySuffix((int)($context['wait_delay_seconds'] ?? 0)));
            }
            if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
                return sprintf('%s，剩余 %d 次', $batchSummary, $remaining);
            }
        }
        if ($afterNode->status() === ActivityNodeStatus::WAITING) {
            return sprintf('抽奖阶段，本次结果：%s，剩余 %d 次%s', $resultLabel, $remaining, $this->formatDelaySuffix((int)($context['wait_delay_seconds'] ?? 0)));
        }
        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('抽奖阶段，本次结果：%s，剩余 %d 次', $resultLabel, $remaining);
        }
        if ($afterNode->status() === ActivityNodeStatus::SKIPPED) {
            return '抽奖阶段，抽奖次数已耗尽';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildRecordDrawSummaryMessage(
        \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        if ($afterNode->status() !== ActivityNodeStatus::SUCCEEDED) {
            return '';
        }

        $total = max(0, (int)($context['draw_total_count'] ?? 0));
        $winCount = max(0, (int)($context['draw_win_count'] ?? 0));
        $wins = is_array($context['draw_win_names'] ?? null) ? $context['draw_win_names'] : [];
        if ($winCount <= 0) {
            return sprintf('抽奖汇总，累计抽奖 %d 次，未命中', $total);
        }

        return sprintf('抽奖汇总，累计抽奖 %d 次，命中 %d 次，奖品：%s', $total, $winCount, implode(' / ', $wins));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function archiveLabel(array $context): string
    {
        $bvid = trim((string)($context['archive_bvid'] ?? ''));
        if ($bvid !== '') {
            return $bvid;
        }

        $aid = trim((string)($context['archive_aid'] ?? ''));
        return $aid !== '' ? 'aid=' . $aid : '';
    }

    private function resolveWaitDelaySeconds(ActivityFlow $flow): int
    {
        $nextRunAt = $flow->nextRunAt();
        if ($nextRunAt <= 0) {
            return 0;
        }

        return max(0, $nextRunAt - $flow->updatedAt());
    }

    private function formatDelaySuffix(int $delaySeconds): string
    {
        if ($delaySeconds <= 0) {
            return '';
        }

        if ($delaySeconds % 3600 === 0 && $delaySeconds >= 3600) {
            return sprintf('，%d 小时后继续', (int)($delaySeconds / 3600));
        }
        if ($delaySeconds % 60 === 0 && $delaySeconds >= 60) {
            return sprintf('，%d 分钟后继续', (int)($delaySeconds / 60));
        }

        return sprintf('，%d 秒后继续', $delaySeconds);
    }

    private function resolveDisplayTargetSeconds(?EraTaskSnapshot $task = null): int
    {
        if ($task === null) {
            return 0;
        }

        $thresholds = EraWatchProgress::thresholds($task);
        if ($thresholds !== []) {
            return max($thresholds);
        }

        return max(0, $task->requiredWatchSeconds());
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>|null
     */
    private function resolveCurrentArchive(?EraTaskSnapshot $task, array $state): ?array
    {
        if (is_array($state['watch_video_archive'] ?? null)) {
            return $state['watch_video_archive'];
        }
        if (is_array($state['topic_archives'] ?? null)) {
            $archives = $state['topic_archives'];
            $index = max(0, (int)($state['topic_archive_index'] ?? 0));
            if (isset($archives[$index]) && is_array($archives[$index])) {
                return $archives[$index];
            }
        }
        if ($task !== null) {
            $archives = $task->targetArchives();
            if ($archives !== []) {
                $index = max(0, (int)($state['fixed_archive_index'] ?? 0));
                if (isset($archives[$index]) && is_array($archives[$index])) {
                    return $archives[$index];
                }
                if (isset($archives[0]) && is_array($archives[0])) {
                    return $archives[0];
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function shouldEmitLifecycleLog(
        string $event,
        string $flowId,
        string $nodeType,
        string $nodeStatus,
        array $context,
        int $now,
    ): bool {
        if ($nodeStatus !== ActivityNodeStatus::WAITING) {
            return true;
        }

        $fingerprint = sha1(json_encode([
            'event' => $event,
            'node_type' => $nodeType,
            'task_id' => $context['task_id'] ?? '',
            'task_name' => $context['task_name'] ?? '',
            'target_uid' => $context['target_uid'] ?? '',
            'follow_completed_count' => $context['follow_completed_count'] ?? 0,
            'follow_total_count' => $context['follow_total_count'] ?? 0,
            'archive_bvid' => $context['archive_bvid'] ?? '',
            'archive_aid' => $context['archive_aid'] ?? '',
            'room_id' => $context['room_id'] ?? 0,
            'local_watch_seconds' => $context['local_watch_seconds'] ?? 0,
            'display_target_seconds' => $context['display_target_seconds'] ?? 0,
            'draw_times_remaining' => $context['draw_times_remaining'] ?? 0,
            'draw_batch_size' => $context['draw_batch_size'] ?? 0,
            'draw_batch_win_count' => $context['draw_batch_win_count'] ?? 0,
            'draw_batch_win_names' => $context['draw_batch_win_names'] ?? [],
            'last_draw_gift_name' => $context['last_draw_gift_name'] ?? '',
            'wait_delay_seconds' => $context['wait_delay_seconds'] ?? 0,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $key = $flowId . '|' . $event . '|' . $nodeType;
        $previous = $this->waitingLogState[$key] ?? null;
        if (is_array($previous)) {
            $sameFingerprint = ($previous['fingerprint'] ?? '') === $fingerprint;
            $withinCooldown = ($now - (int)($previous['logged_at'] ?? 0)) < 60;
            if ($sameFingerprint && $withinCooldown) {
                return false;
            }
        }

        $this->waitingLogState[$key] = [
            'fingerprint' => $fingerprint,
            'logged_at' => $now,
        ];

        return true;
    }

}
