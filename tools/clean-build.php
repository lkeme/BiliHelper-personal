<?php declare(strict_types=1);

$projectRoot = dirname(__DIR__);

$targets = [
    $projectRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'build',
    $projectRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'logs',
    $projectRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'phpstan',
    $projectRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'phpstan-plugin-probe',
    $projectRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'phpstan-plugin-probe.neon',
];

foreach ($targets as $target) {
    removeTarget($target);
}

fwrite(STDOUT, "build-clean-ok" . PHP_EOL);

function removeTarget(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }

    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    /** @var SplFileInfo $item */
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            @rmdir($item->getPathname());
            continue;
        }

        @unlink($item->getPathname());
    }

    @rmdir($path);
}
