<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Notice\Notice;
use Bhp\Runtime\AppContext;
use Bhp\Util\AppTerminator;

final class LoginManualInterventionPolicy
{
    public function __construct(
        private readonly ?AppContext $context = null,
        private readonly ?LoginPendingFlowStore $pendingFlowStore = null,
        private readonly mixed $clock = null,
        private readonly mixed $noticePusher = null,
        private readonly mixed $terminator = null,
        private readonly ?string $modeOverride = null,
        private readonly ?int $notifyAfterSecondsOverride = null,
        private readonly ?int $notifyIntervalSecondsOverride = null,
        private readonly ?int $timeoutSecondsOverride = null,
    ) {
    }

    public function enforce(): void
    {
        $flow = $this->pendingFlowStore()->load();
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
            $this->pendingFlowStore()->save($flow);
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

    private function shouldNotify(array $flow, int $now): bool
    {
        $lastNotifiedAt = (int)($flow['last_notified_at'] ?? 0);
        if ($lastNotifiedAt <= 0) {
            return true;
        }

        return ($now - $lastNotifiedAt) >= $this->notifyIntervalSeconds();
    }

    private function isUnattended(): bool
    {
        $mode = $this->modeOverride;
        if ($mode === null || $mode === '') {
            $mode = (string)$this->context()->config('login_policy.mode', 'auto');
        }

        return match ($mode) {
            'interactive' => false,
            'unattended' => true,
            default => !$this->isInteractiveConsole(),
        };
    }

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

    private function notifyAfterSeconds(): int
    {
        if ($this->notifyAfterSecondsOverride !== null) {
            return max(0, $this->notifyAfterSecondsOverride);
        }

        return max(0, (int)$this->context()->config('login_policy.notify_after_seconds', 60));
    }

    private function notifyIntervalSeconds(): int
    {
        if ($this->notifyIntervalSecondsOverride !== null) {
            return max(1, $this->notifyIntervalSecondsOverride);
        }

        return max(1, (int)$this->context()->config('login_policy.notify_interval_seconds', 300));
    }

    private function timeoutSeconds(): int
    {
        if ($this->timeoutSecondsOverride !== null) {
            return max(1, $this->timeoutSecondsOverride);
        }

        return max(1, (int)$this->context()->config('login_policy.pending_timeout_seconds', 900));
    }

    private function notify(string $type, string $message): void
    {
        if (is_callable($this->noticePusher)) {
            ($this->noticePusher)($type, $message);
            return;
        }

        Notice::push($type, $message);
    }

    private function terminate(string $message): void
    {
        if (is_callable($this->terminator)) {
            ($this->terminator)($message);
            return;
        }

        AppTerminator::fail($message, [], 0);
    }

    private function now(): int
    {
        if (is_callable($this->clock)) {
            return (int)($this->clock)();
        }

        return time();
    }

    private function context(): AppContext
    {
        return $this->context ?? new AppContext();
    }

    private function pendingFlowStore(): LoginPendingFlowStore
    {
        return $this->pendingFlowStore ?? new LoginPendingFlowStore();
    }
}
