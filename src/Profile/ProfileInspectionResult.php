<?php declare(strict_types=1);

namespace Bhp\Profile;

final class ProfileInspectionResult
{
    /**
     * 初始化 ProfileInspectionResult
     * @param string $profile
     * @param bool $configDir
     * @param bool $userIni
     * @param bool $deviceDefault
     * @param string $deviceOverride
     * @param bool $logDir
     * @param bool $cacheDir
     * @param bool $logWritable
     * @param bool $cacheWritable
     * @param bool $cacheDatabase
     */
    public function __construct(
        public readonly string $profile,
        public readonly bool $configDir,
        public readonly bool $userIni,
        public readonly bool $deviceDefault,
        public readonly string $deviceOverride,
        public readonly bool $logDir,
        public readonly bool $cacheDir,
        public readonly bool $logWritable = false,
        public readonly bool $cacheWritable = false,
        public readonly bool $cacheDatabase = false,
    ) {
    }

    /**
     * 判断Healthy是否满足条件
     * @return bool
     */
    public function isHealthy(): bool
    {
        return $this->configDir
            && $this->userIni
            && $this->deviceDefault
            && $this->logDir
            && $this->cacheDir
            && $this->logWritable
            && $this->cacheWritable;
    }

    /**
     * @return array<string, string>
     */
    public function diagnostics(): array
    {
        return [
            'profile' => $this->profile,
            'status' => $this->isHealthy() ? 'OK' : 'WARN',
            'config_dir' => $this->formatBool($this->configDir),
            'user_ini' => $this->formatBool($this->userIni),
            'device_default' => $this->formatBool($this->deviceDefault),
            'device_override' => $this->deviceOverride,
            'log_dir' => $this->formatBool($this->logDir),
            'log_writable' => $this->formatBool($this->logWritable),
            'cache_dir' => $this->formatBool($this->cacheDir),
            'cache_writable' => $this->formatBool($this->cacheWritable),
            'cache_database' => $this->formatBool($this->cacheDatabase),
        ];
    }

    /**
     * 格式化Bool
     * @param bool $value
     * @return string
     */
    private function formatBool(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
