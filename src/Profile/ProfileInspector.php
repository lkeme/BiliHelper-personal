<?php declare(strict_types=1);

namespace Bhp\Profile;

class ProfileInspector
{
    public function inspect(ProfileContext $profile): ProfileInspectionResult
    {
        $logWritable = $this->isDirectoryWritable($profile->logPath());
        $cacheWritable = $this->isDirectoryWritable($profile->cachePath());
        $deviceDefault = is_file($this->defaultDevicePath($profile));
        $cacheDatabase = is_file($profile->cachePath() . 'cache.sqlite3');

        return new ProfileInspectionResult(
            $profile->name(),
            is_dir($profile->configPath()),
            is_file($profile->configPath() . 'user.ini'),
            $deviceDefault,
            $this->deviceOverrideMode($profile),
            is_dir($profile->logPath()),
            is_dir($profile->cachePath()),
            $logWritable,
            $cacheWritable,
            $cacheDatabase,
        );
    }

    protected function isDirectoryWritable(string $path): bool
    {
        return is_dir($path) && is_writable($path);
    }

    protected function defaultDevicePath(ProfileContext $profile): string
    {
        return rtrim(str_replace('\\', '/', $profile->appRoot()), '/') . '/resources/device/default.yaml';
    }

    protected function deviceOverrideMode(ProfileContext $profile): string
    {
        $replaceOverride = $profile->configPath() . 'device.override.yaml';
        $mergeOverride = $profile->configPath() . 'device.override+.yaml';

        if (is_file($replaceOverride)) {
            return 'replace';
        }

        if (is_file($mergeOverride)) {
            return 'merge';
        }

        return 'none';
    }
}
