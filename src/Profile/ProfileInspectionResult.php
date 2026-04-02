<?php declare(strict_types=1);

namespace Bhp\Profile;

final class ProfileInspectionResult
{
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
    ) {
    }

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
        ];
    }

    private function formatBool(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
