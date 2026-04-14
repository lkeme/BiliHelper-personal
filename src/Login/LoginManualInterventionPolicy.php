<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Notice\Notice;
use Bhp\Runtime\AppContext;
use Bhp\Util\AppTerminator;

final class LoginManualInterventionPolicy
{
    /**
     * 初始化 LoginManualInterventionPolicy
     * @param AppContext $context
     * @param LoginPendingFlowStore $pendingFlowStore
     * @param Notice $notice
     * @param mixed $clock
     * @param mixed $noticePusher
     * @param mixed $terminator
     * @param string $modeOverride
     * @param int $notifyAfterSecondsOverride
     * @param int $notifyIntervalSecondsOverride
     * @param int $timeoutSecondsOverride
     */
    public function __construct(
        private readonly AppContext $context,
        private readonly LoginPendingFlowStore $pendingFlowStore,
        private readonly ?Notice $notice = null,
        private readonly mixed $clock = null,
        private readonly mixed $noticePusher = null,
        private readonly mixed $terminator = null,
        private readonly ?string $modeOverride = null,
        private readonly ?int $notifyAfterSecondsOverride = null,
        private readonly ?int $notifyIntervalSecondsOverride = null,
        private readonly ?int $timeoutSecondsOverride = null,
    ) {
    }

    /**
     * 处理enforce
     * @return void
     */
    public function enforce(): void
    {
        $flow = $this->pendingFlowStore->load();
        if (!is_array($flow)) {
            return;
        }

        $now = $this->now();
        $startedAt = (int)($flow['started_at'] ?? $now);
        $age = max(0, $now - $startedAt);
        $type = trim((string)($flow['type'] ?? 'unknown'));

        if ($age >= $this->notifyAfterSeconds() && $this->shouldNotify($flow, $now)) {
            $this->notify(
                'login_pending',
                sprintf('登录流程等待人工介入 type=%s 已等待 %d 秒', $type, $age),
            );
            $flow['last_notified_at'] = $now;
            $this->pendingFlowStore->save($flow);
        }

        if (!$this->isUnattended()) {
            return;
        }

        if ($age < $this->timeoutSeconds()) {
            return;
        }

        $this->notify(
            'login_pending_timeout',
            sprintf('登录流程超时未完成 type=%s 已等待 %d 秒，系统将退出', $type, $age),
        );
        $this->terminate(sprintf('登录流程需要人工介入，超时退出: %s', $type));
    }

    /**
     * 判断通知是否满足条件
     * @param array $flow
     * @param int $now
     * @return bool
     */
    private function shouldNotify(array $flow, int $now): bool
    {
        $lastNotifiedAt = (int)($flow['last_notified_at'] ?? 0);
        if ($lastNotifiedAt <= 0) {
            return true;
        }

        return ($now - $lastNotifiedAt) >= $this->notifyIntervalSeconds();
    }

    /**
     * 判断Unattended是否满足条件
     * @return bool
     */
    private function isUnattended(): bool
    {
        $mode = $this->modeOverride;
        if ($mode === null || $mode === '') {
            $mode = (string)$this->context->config('login_policy.mode', 'auto');
        }

        return match ($mode) {
            'interactive' => false,
            'unattended' => true,
            default => !$this->isInteractiveConsole(),
        };
    }

    /**
     * 判断InteractiveConsole是否满足条件
     * @return bool
     */
    private function isInteractiveConsole(): bool
    {
        if (!defined('STDIN')) {
            return false;
        }

        if (function_exists('stream_isatty')) {
            return @stream_isatty(STDIN);
        }

        return false;
    }

    /**
     * 处理通知AfterSeconds
     * @return int
     */
    private function notifyAfterSeconds(): int
    {
        if ($this->notifyAfterSecondsOverride !== null) {
            return max(0, $this->notifyAfterSecondsOverride);
        }

        return max(0, (int)$this->context->config('login_policy.notify_after_seconds', 60));
    }

    /**
     * 处理通知IntervalSeconds
     * @return int
     */
    private function notifyIntervalSeconds(): int
    {
        if ($this->notifyIntervalSecondsOverride !== null) {
            return max(1, $this->notifyIntervalSecondsOverride);
        }

        return max(1, (int)$this->context->config('login_policy.notify_interval_seconds', 300));
    }

    /**
     * 处理timeoutSeconds
     * @return int
     */
    private function timeoutSeconds(): int
    {
        if ($this->timeoutSecondsOverride !== null) {
            return max(1, $this->timeoutSecondsOverride);
        }

        return max(1, (int)$this->context->config('login_policy.pending_timeout_seconds', 900));
    }

    /**
     * 处理通知
     * @param string $type
     * @param string $message
     * @return void
     */
    private function notify(string $type, string $message): void
    {
        if (is_callable($this->noticePusher)) {
            ($this->noticePusher)($type, $message);
            return;
        }

        if ($this->notice instanceof Notice) {
            $this->notice->publish($type, $message);
            return;
        }

        throw new \LogicException('LoginManualInterventionPolicy notice dependency is not configured.');
    }

    /**
     * 处理terminate
     * @param string $message
     * @return void
     */
    private function terminate(string $message): void
    {
        if (is_callable($this->terminator)) {
            ($this->terminator)($message);
            return;
        }

        AppTerminator::fail($message, [], 0);
    }

    /**
     * 获取当前时间
     * @return int
     */
    private function now(): int
    {
        if (is_callable($this->clock)) {
            return (int)($this->clock)();
        }

        return time();
    }
}
