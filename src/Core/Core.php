<?php declare(strict_types=1);

namespace Bhp\Core;

use Bhp\Console\Cli\RuntimeException as CliRuntimeException;
use Bhp\Profile\ProfileContext;
use Bhp\Util\Os\Path;

final class Core
{
    /**
     * 初始化 Core
     * @param ProfileContext $profileContext
     */
    public function __construct(
        private readonly ProfileContext $profileContext,
    ) {
        $this->initSystemPath();
        $this->initSystemConstant();
    }

    /**
     * 初始化SystemConstant
     * @return void
     */
    protected function initSystemConstant(): void
    {
        $this->defineConstant('APP_MICROSECOND', 1000000);

        if (!is_dir($this->nativePath($this->profileContext->configPath()))) {
            throw new CliRuntimeException("加载 {$this->profileContext->name()} 用户文档失败，请检查路径！");
        }
    }

    /**
     * 处理defineConstant
     * @param string $name
     * @param mixed $value
     * @return void
     */
    protected function defineConstant(string $name, mixed $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * 初始化SystemPath
     * @return void
     */
    protected function initSystemPath(): void
    {
        $legacyDevicePath = $this->nativePath($this->profileContext->rootPath() . 'device');
        if (is_dir($legacyDevicePath)) {
            Path::RemoveDirectoryRecursively($legacyDevicePath);
        }

        $system_paths = [
            $this->nativePath($this->profileContext->configPath()),
            $this->nativePath($this->profileContext->rootPath() . 'resources'),
            $this->nativePath($this->profileContext->rootPath() . 'resources/device'),
            $this->nativePath($this->profileContext->logPath()),
            $this->nativePath($this->profileContext->cachePath()),
        ];
        foreach ($system_paths as $path) {
            if (!file_exists($path)) {
                Path::CreateFolder($path);
                Path::SetFolderPermissions($path);
            }
        }
    }

    /**
     * 处理nativePath
     * @param string $path
     * @return string
     */
    protected function nativePath(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
