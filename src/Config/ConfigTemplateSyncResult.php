<?php declare(strict_types=1);

namespace Bhp\Config;

final class ConfigTemplateSyncResult
{
    /**
     * 初始化 ConfigTemplateSyncResult
     * @param bool $changed
     * @param string $backupPath
     */
    public function __construct(
        public readonly bool $changed,
        public readonly ?string $backupPath = null,
    ) {
    }
}
