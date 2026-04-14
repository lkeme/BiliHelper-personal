<?php declare(strict_types=1);

namespace Bhp\Profile;

class ProfileInspector
{
    /**
     * 处理inspect
     * @param ProfileContext $profile
     * @return ProfileInspectionResult
     */
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

    /**
     * 判断DirectoryWritable是否满足条件
     * @param string $path
     * @return bool
     */
    protected function isDirectoryWritable(string $path): bool
    {
        return is_dir($path) && is_writable($path);
    }

    /**
     * 处理defaultDevicePath
     * @param ProfileContext $profile
     * @return string
     */
    protected function defaultDevicePath(ProfileContext $profile): string
    {
        return rtrim(str_replace('\\', '/', $profile->appRoot()), '/') . '/resources/device/default.yaml';
    }

    /**
     * 处理deviceOverride模式
     * @param ProfileContext $profile
     * @return string
     */
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
