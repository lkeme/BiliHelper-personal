<?php declare(strict_types=1);

namespace Bhp\Console\Command;

use Bhp\Console\Cli\Command;
use Bhp\Console\Cli\Interactor;
use Bhp\Console\Cli\RuntimeException as CliRuntimeException;
use Bhp\Log\Log;
use Bhp\Plugin\Plugin;
use Bhp\Profile\ProfileContext;
use Bhp\Profile\ProfileInspectionResult;
use Bhp\Profile\ProfileInspector;
use Bhp\Profile\ProfileRegistry;
use Bhp\Remote\RemoteResourceResolver;
use Bhp\Http\HttpRequestTrafficMonitor;
use Bhp\Request\Request;
use Bhp\Runtime\Runtime;
use Bhp\Http\HttpClientInterceptorRegistry;
use Bhp\Scheduler\Scheduler;
use Bhp\Util\AsciiTable\AsciiTable;

class DoctorCommand extends Command
{
    protected string $desc = '[Doctor模式] 检查 profile 运行条件';

    public function __construct()
    {
        parent::__construct('mode:doctor', $this->desc);
        $this
            ->option('-p --profiles', '指定 profile 列表，逗号分隔')
            ->option('-a --all', '检查全部 profile')
            ->option('-f --format', '输出格式: table|json')
            ->option('-o --output', '将 JSON 输出写入文件')
            ->usage(
                '<bold>  $0</end> <comment>user mode:doctor</end><eol/>' .
                '<bold>  $0</end> <comment>user m:o</end><eol/>' .
                '<bold>  $0</end> <comment>mode:doctor --all</end><eol/>' .
                '<bold>  $0</end> <comment>m:o -a</end><eol/>' .
                '<bold>  $0</end> <comment>mode:doctor --profiles alpha,beta</end><eol/>' .
                '<bold>  $0</end> <comment>mode:doctor --all --format json</end><eol/>' .
                '<bold>  $0</end> <comment>mode:doctor --all --format json --output build/logs/doctor.json</end><eol/>'
            );
    }

    public function interact(Interactor $io): void
    {
    }

    public function execute(): void
    {
        Log::withContext(['caller' => 'DoctorCommand'], function (): void {
            Log::info("执行 {$this->desc}");
            $profiles = $this->resolveProfiles();
            if ($profiles === []) {
                throw new CliRuntimeException('没有可检查的 profile');
            }

            $results = $this->createInspector()->inspectMany($profiles);
            $rows = array_map(static fn(ProfileInspectionResult $result): array => $result->toArray(), $results);
            if ($this->isJsonFormat()) {
                $this->renderJsonReport($rows);
            } else {
                AsciiTable::array2table(
                    array_map(static fn(ProfileInspectionResult $result): array => $result->toDisplayArray(), $results),
                    'Profile 检查结果',
                    true
                );
                $this->renderRuntimeDiagnostics();
            }

            $failed = array_values(array_map(
                static fn(ProfileInspectionResult $result): string => $result->profile,
                array_filter($results, static fn(ProfileInspectionResult $result): bool => !$result->isHealthy())
            ));

            if ($failed !== []) {
                throw new CliRuntimeException('以下 profile 存在基础缺项: ' . implode(', ', $failed));
            }
        });
    }

    protected function createInspector(): ProfileInspector
    {
        return new ProfileInspector();
    }

    protected function renderRuntimeDiagnostics(): void
    {
        $summaryRows = $this->collectRuntimeSummaryRows();
        if ($summaryRows !== []) {
            $this->renderKeyValueSection('当前运行时诊断', $summaryRows[0]);
        }

        $requestSummaryRows = $this->collectRuntimeRequestSummaryRows();
        if ($requestSummaryRows !== []) {
            $this->renderKeyValueSection('当前运行时请求诊断', $requestSummaryRows[0]);
        }

        $requestProviderRows = $this->collectRuntimeRequestProviderRows();
        if ($requestProviderRows !== []) {
            AsciiTable::array2table($requestProviderRows, '当前运行时请求 Provider 链', true);
        }

        $requestTrafficRows = $this->collectRuntimeRequestTrafficRows();
        if ($requestTrafficRows !== []) {
            AsciiTable::array2table($requestTrafficRows, '当前运行时请求热点主机', true);
        }

        $pluginFailureRows = $this->collectRuntimePluginFailureRows();
        if ($pluginFailureRows !== []) {
            AsciiTable::array2table($pluginFailureRows, '当前运行时插件装配异常', true);
        }

        $schedulerIssueRows = $this->collectRuntimeSchedulerIssueRows();
        if ($schedulerIssueRows !== []) {
            AsciiTable::array2table($schedulerIssueRows, '当前运行时调度异常', true);
        }
    }

    /**
     * @param array<string, string> $row
     */
    protected function renderKeyValueSection(string $title, array $row): void
    {
        echo '【' . $title . '】' . PHP_EOL;
        foreach ($row as $key => $value) {
            echo $key . ': ' . $this->truncateDisplayValue((string)$value) . PHP_EOL;
        }
    }

    protected function truncateDisplayValue(string $value, int $maxWidth = 88): string
    {
        if (mb_strlen($value) <= $maxWidth) {
            return $value;
        }

        return mb_substr($value, 0, max(0, $maxWidth - 1)) . '…';
    }

    /**
     * @param array<int, array<string, mixed>> $profileRows
     */
    protected function renderJsonReport(array $profileRows): void
    {
        $payload = [
            'profiles' => $profileRows,
            'runtime_summary' => $this->collectRuntimeSummaryRows(),
            'request_summary' => $this->collectRuntimeRequestSummaryRows(),
            'request_providers' => $this->collectRuntimeRequestProviderRows(),
            'request_traffic' => $this->collectRuntimeRequestTrafficRows(),
            'plugin_failures' => $this->collectRuntimePluginFailureRows(),
            'scheduler_issues' => $this->collectRuntimeSchedulerIssueRows(),
        ];

        $json = (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $output = trim((string)($this->values()['output'] ?? ''));
        if ($output !== '') {
            $this->writeJsonReport($output, $json);
        }

        echo $json . PHP_EOL;
    }

    protected function isJsonFormat(): bool
    {
        return strtolower(trim((string)($this->values()['format'] ?? 'table'))) === 'json';
    }

    protected function writeJsonReport(string $path, string $json): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $json);
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function collectRuntimeSummaryRows(): array
    {
        $scheduler = $this->runtimeScheduler();
        $scheduler->registerPlugins($this->runtimePlugins());
        $summary = $scheduler->diagnosticsSummary();
        $registry = $this->runtimePluginRegistry();
        $requestSummary = $this->runtimeRequest()->diagnosticsSummary();
        $providerSummary = $this->runtimeInterceptorRegistry()->diagnosticsSummary();
        $trafficSummary = $this->runtimeTrafficMonitor()->diagnosticsSummary();
        $branchResolver = new RemoteResourceResolver();

        return [[
            'runtime_profile' => Runtime::getInstance()->appContext()->profileName(),
            'branch' => $branchResolver->branch(),
            'branch_override' => $branchResolver->overrideBranch() ?? '-',
            'configured_app_branch' => $branchResolver->configuredBranch() ?? '-',
            'remote_resource_branch' => $branchResolver->branch(),
            'plugins_total' => (string)count($this->runtimePlugins()),
            'plugins_registered' => (string)count(array_filter($registry, static fn(array $plugin): bool => ($plugin['status'] ?? '') === 'registered')),
            'plugins_failed' => (string)count(array_filter($registry, static fn(array $plugin): bool => (string)($plugin['error'] ?? '') !== '')),
            'scheduler_tasks' => (string)$summary['scheduler_tasks'],
            'high_frequency_tasks' => (string)$summary['high_frequency_tasks'],
            'bootstrap_first_tasks' => (string)$summary['bootstrap_first_tasks'],
            'concurrent_tasks' => (string)$summary['concurrent_tasks'],
            'open_circuits' => (string)$summary['open_circuits'],
            'failing_tasks' => (string)$summary['failing_tasks'],
            'request_timeout_seconds' => $requestSummary['timeout_seconds'],
            'request_retry_attempts' => $requestSummary['retry_attempts'],
            'request_providers' => $providerSummary['provider_count'],
            'recent_requests' => $trafficSummary['recent_request_total'],
            'recent_request_failures' => $trafficSummary['recent_request_failures'],
            'request_governance' => $requestSummary['governance_enabled'],
        ]];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function collectRuntimeRequestSummaryRows(): array
    {
        $requestSummary = $this->runtimeRequest()->diagnosticsSummary();
        $providerSummary = $this->runtimeInterceptorRegistry()->diagnosticsSummary();
        $trafficSummary = $this->runtimeTrafficMonitor()->diagnosticsSummary();

        return [[
            'timeout_seconds' => $requestSummary['timeout_seconds'],
            'retry_attempts' => $requestSummary['retry_attempts'],
            'retry_sequence' => $requestSummary['retry_sequence'],
            'retry_last' => $requestSummary['retry_last'],
            'proxy_enabled' => $requestSummary['proxy_enabled'],
            'buvid_cached' => $requestSummary['buvid_cached'],
            'provider_count' => $providerSummary['provider_count'],
            'provider_chain' => $providerSummary['provider_chain'],
            'traffic_window_seconds' => $trafficSummary['traffic_window_seconds'],
            'recent_request_total' => $trafficSummary['recent_request_total'],
            'recent_request_failures' => $trafficSummary['recent_request_failures'],
            'governance_enabled' => $requestSummary['governance_enabled'],
            'governance_mode' => $requestSummary['governance_mode'],
            'governance_window_seconds' => $requestSummary['governance_window_seconds'],
            'governance_max_requests_per_host' => $requestSummary['governance_max_requests_per_host'],
            'governance_cooldown_seconds' => $requestSummary['governance_cooldown_seconds'],
        ]];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function collectRuntimeRequestProviderRows(): array
    {
        return $this->runtimeInterceptorRegistry()->diagnosticsRows();
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function collectRuntimeRequestTrafficRows(): array
    {
        return $this->runtimeTrafficMonitor()->diagnosticsHostRows();
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function collectRuntimePluginFailureRows(): array
    {
        $rows = [];
        foreach ($this->runtimePluginRegistry() as $plugin) {
            if ((string)($plugin['error'] ?? '') === '') {
                continue;
            }

            $rows[] = [
                'hook' => (string)($plugin['hook'] ?? ''),
                'status' => (string)($plugin['status'] ?? ''),
                'error' => (string)($plugin['error'] ?? ''),
                'path' => (string)($plugin['path'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function collectRuntimeSchedulerIssueRows(): array
    {
        $scheduler = $this->runtimeScheduler();
        $scheduler->registerPlugins($this->runtimePlugins());

        return $scheduler->diagnosticsIssueRows();
    }

    protected function runtimeScheduler(): Scheduler
    {
        return Scheduler::getInstance();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function runtimePluginRegistry(): array
    {
        return Plugin::getRegistry();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function runtimePlugins(): array
    {
        return Plugin::getPlugins();
    }

    protected function runtimeRequest(): Request
    {
        return Request::getInstance();
    }

    protected function runtimeInterceptorRegistry(): HttpClientInterceptorRegistry
    {
        return HttpClientInterceptorRegistry::getInstance();
    }

    protected function runtimeTrafficMonitor(): HttpRequestTrafficMonitor
    {
        return HttpRequestTrafficMonitor::getInstance();
    }

    /**
     * @return ProfileContext[]
     */
    protected function resolveProfiles(): array
    {
        $all = (bool)($this->values()['all'] ?? false);
        $input = trim((string)($this->values()['profiles'] ?? ''));
        $registry = new ProfileRegistry(Runtime::getInstance()->appContext()->appRoot());

        if ($all) {
            return array_values($registry->discover());
        }

        if ($input === '') {
            return [$registry->resolve(Runtime::getInstance()->appContext()->profileName())];
        }

        $profiles = [];
        foreach (preg_split('/[|,]/', $input, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $name) {
            $profiles[] = $registry->resolve(trim($name));
        }

        return $profiles;
    }
}
