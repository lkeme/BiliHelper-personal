<?php declare(strict_types=1);

namespace Bhp\Config;

final class ConfigTemplateSyncResult
{
    public function __construct(
        public readonly bool $changed,
        public readonly ?string $backupPath = null,
    ) {
    }
}
