<?php declare(strict_types=1);

namespace Bhp\Http;

class HttpRequestTrafficMonitor
{
    private const DEFAULT_WINDOW_SECONDS = 300;

    /**
     * @var array<int, array{timestamp: float, host: string, success: bool}>
     */
    private array $samples = [];

    /**
     * 处理初始化
     * @return void
     */
    public function init(): void
    {
        $this->samples = [];
    }

    /**
     * 处理record
     * @param string $host
     * @param bool $success
     * @return void
     */
    public function record(string $host, bool $success): void
    {
        $this->samples[] = [
            'timestamp' => microtime(true),
            'host' => $host,
            'success' => $success,
        ];

        $this->prune(self::DEFAULT_WINDOW_SECONDS);
    }

    /**
     * 处理主机请求数量
     * @param string $host
     * @param int $windowSeconds
     * @return int
     */
    public function hostRequestCount(string $host, int $windowSeconds = self::DEFAULT_WINDOW_SECONDS): int
    {
        return count(array_filter(
            $this->recentSamples($windowSeconds),
            static fn(array $sample): bool => $sample['host'] === $host,
        ));
    }

    /**
     * 处理cooldownRemaining
     * @param string $host
     * @param int $windowSeconds
     * @param int $maxRequestsPerHost
     * @param int $cooldownSeconds
     * @return float
     */
    public function cooldownRemaining(
        string $host,
        int $windowSeconds,
        int $maxRequestsPerHost,
        int $cooldownSeconds,
    ): float {
        if ($maxRequestsPerHost < 1) {
            return 0.0;
        }

        $samples = array_values(array_filter(
            $this->recentSamples($windowSeconds),
            static fn(array $sample): bool => $sample['host'] === $host,
        ));

        if (count($samples) < $maxRequestsPerHost) {
            return 0.0;
        }

        $reference = $samples[count($samples) - $maxRequestsPerHost]['timestamp'] ?? null;
        if (!is_float($reference)) {
            return 0.0;
        }

        return max(0.0, ((float)$reference + $cooldownSeconds) - microtime(true));
    }

    /**
     * @return array<string, string>
     */
    public function diagnosticsSummary(int $windowSeconds = self::DEFAULT_WINDOW_SECONDS): array
    {
        $samples = $this->recentSamples($windowSeconds);
        $total = count($samples);
        $failures = count(array_filter($samples, static fn(array $sample): bool => !$sample['success']));
        $hosts = [];
        foreach ($samples as $sample) {
            $host = $sample['host'] !== '' ? $sample['host'] : 'unknown';
            $hosts[$host] = ($hosts[$host] ?? 0) + 1;
        }

        arsort($hosts);
        $topHost = $hosts === [] ? '' : (string)array_key_first($hosts);
        $topHostCount = $hosts === [] ? 0 : (int)reset($hosts);

        return [
            'traffic_window_seconds' => (string)$windowSeconds,
            'recent_request_total' => (string)$total,
            'recent_request_failures' => (string)$failures,
            'recent_unique_hosts' => (string)count($hosts),
            'recent_top_host' => $topHost,
            'recent_top_host_hits' => (string)$topHostCount,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function diagnosticsHostRows(int $windowSeconds = self::DEFAULT_WINDOW_SECONDS, int $limit = 5): array
    {
        $samples = $this->recentSamples($windowSeconds);
        $stats = [];

        foreach ($samples as $sample) {
            $host = $sample['host'] !== '' ? $sample['host'] : 'unknown';
            $stats[$host] ??= [
                'host' => $host,
                'requests' => 0,
                'failures' => 0,
            ];
            $stats[$host]['requests']++;
            if (!$sample['success']) {
                $stats[$host]['failures']++;
            }
        }

        usort($stats, static function (array $left, array $right): int {
            $byRequests = $right['requests'] <=> $left['requests'];
            if ($byRequests !== 0) {
                return $byRequests;
            }

            return $right['failures'] <=> $left['failures'];
        });

        $rows = [];
        foreach (array_slice($stats, 0, $limit) as $item) {
            $rows[] = [
                'host' => (string)$item['host'],
                'requests' => (string)$item['requests'],
                'failures' => (string)$item['failures'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{timestamp: float, host: string, success: bool}>
     */
    private function recentSamples(int $windowSeconds): array
    {
        $this->prune($windowSeconds);
        $cutoff = microtime(true) - max(1, $windowSeconds);

        return array_values(array_filter(
            $this->samples,
            static fn(array $sample): bool => $sample['timestamp'] >= $cutoff,
        ));
    }

    /**
     * 处理prune
     * @param int $windowSeconds
     * @return void
     */
    private function prune(int $windowSeconds): void
    {
        $cutoff = microtime(true) - max(1, $windowSeconds);
        $this->samples = array_values(array_filter(
            $this->samples,
            static fn(array $sample): bool => $sample['timestamp'] >= $cutoff,
        ));
    }
}
