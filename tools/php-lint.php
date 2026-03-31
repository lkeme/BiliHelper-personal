<?php declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "用法: php tools/php-lint.php <path> [<path> ...]\n");
    exit(1);
}

$paths = array_slice($argv, 1);
$files = [];

foreach ($paths as $path) {
    if (is_file($path) && str_ends_with($path, '.php')) {
        $files[] = realpath($path) ?: $path;
        continue;
    }

    if (!is_dir($path)) {
        fwrite(STDERR, "路径不存在: {$path}\n");
        exit(1);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
            $files[] = $file->getPathname();
        }
    }
}

$files = array_values(array_unique($files));
sort($files);

foreach ($files as $file) {
    $command = sprintf('php -l %s', escapeshellarg($file));
    exec($command, $output, $code);

    if ($code !== 0) {
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
        exit($code);
    }
}

fwrite(STDOUT, "lint-ok" . PHP_EOL);
