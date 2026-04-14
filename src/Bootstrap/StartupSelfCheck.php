<?php declare(strict_types=1);

namespace Bhp\Bootstrap;

use Bhp\Http\HttpRequestTrafficMonitor;
use Bhp\Login\LoginGateStateService;
use Bhp\Plugin\Plugin;
use Bhp\Profile\ProfileInspector;
use Bhp\Runtime\AppContext;
use Bhp\Util\AppTerminator;

final class StartupSelfCheck
{
    /**
     * 初始化 StartupSelfCheck
     * @param AppContext $context
     * @param ProfileInspector $profileInspector
     * @param Plugin $plugin
     * @param LoginGateStateService $loginGateStateService
     * @param HttpRequestTrafficMonitor $httpRequestTrafficMonitor
     */
    public function __construct(
        private readonly AppContext $context,
        private readonly ?ProfileInspector $profileInspector = null,
        private readonly ?Plugin $plugin = null,
        private readonly ?LoginGateStateService $loginGateStateService = null,
        private readonly ?HttpRequestTrafficMonitor $httpRequestTrafficMonitor = null,
    ) {
    }

    /**
     * 启动执行流程
     * @return void
     */
    public function run(): void
    {
        $report = $this->report();
        if (!$report->hasBlockingIssues()) {
            return;
        }

        $details = [];
        foreach ($report->diagnostics() as $key => $value) {
            if ($key === 'profile' || $key === 'status') {
                continue;
            }

            $details[] = $key . '=' . $value;
        }

        AppTerminator::fail('启动自检失败: ' . implode(', ', $details));
    }

    /**
     * 处理report
     * @return StartupSelfCheckReport
     */
    public function report(): StartupSelfCheckReport
    {
        $profile = $this->context->profileContext();
        $profileReport = $this->profileInspector()->inspect($profile);
        $pluginRegistry = $this->plugin?->registry() ?? [];
        $failedOfficial = array_filter($pluginRegistry, static function (array $entry): bool {
            $status = (string)($entry['status'] ?? '');
            $vendor = (string)($entry['vendor'] ?? '');
            $source = (string)($entry['source'] ?? '');

            return in_array($status, ['failed', 'missing'], true)
                && in_array($vendor !== '' ? $vendor : $source, ['official', 'core'], true);
        });

        $blockingIssues = [];
        if (!$profileReport->isHealthy()) {
            $blockingIssues[] = 'profile';
        }
        foreach ($failedOfficial as $hook => $entry) {
            $blockingIssues[] = 'plugin:' . $hook;
        }

        return new StartupSelfCheckReport(
            $profileReport,
            [
                'state' => $this->loginGateStateService?->state() ?? 'unknown',
                'auth_ready' => ($this->loginGateStateService?->authReady() ?? false) ? 'yes' : 'no',
                'pending_flow' => ($this->loginGateStateService?->hasPendingFlow() ?? false) ? 'yes' : 'no',
            ],
            [
                'total' => (string)count($pluginRegistry),
                'registered' => (string)$this->countPluginStatus($pluginRegistry, 'registered'),
                'discovered' => (string)$this->countPluginStatus($pluginRegistry, 'discovered'),
                'failed' => (string)$this->countPluginStatus($pluginRegistry, 'failed'),
                'missing' => (string)$this->countPluginStatus($pluginRegistry, 'missing'),
                'skipped' => (string)$this->countPluginStatus($pluginRegistry, 'skipped'),
                'failed_official' => (string)count($failedOfficial),
            ],
            [
                'governance_enabled' => $this->context->config('request_governance.enable', false, 'bool') ? 'yes' : 'no',
                'governance_mode' => (string)$this->context->config('request_governance.mode', 'observe'),
                'governance_window_seconds' => (string)$this->context->config('request_governance.window_seconds', 60, 'int'),
                'governance_max_requests_per_host' => (string)$this->context->config('request_governance.max_requests_per_host', 60, 'int'),
                'governance_cooldown_seconds' => (string)$this->context->config('request_governance.cooldown_seconds', 30, 'int'),
            ],
            $this->httpRequestTrafficMonitor?->diagnosticsSummary() ?? [],
            $blockingIssues,
        );
    }

    /**
     * 处理画像Inspector
     * @return ProfileInspector
     */
    private function profileInspector(): ProfileInspector
    {
        return $this->profileInspector ?? new ProfileInspector();
    }

    /**
     * @param array<string, array<string, mixed>> $pluginRegistry
     */
    private function countPluginStatus(array $pluginRegistry, string $status): int
    {
        return count(array_filter(
            $pluginRegistry,
            static fn(array $entry): bool => (string)($entry['status'] ?? '') === $status,
        ));
    }
}
