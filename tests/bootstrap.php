<?php declare(strict_types=1);

$autoload = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException(
        sprintf('PHPUnit bootstrap failed: vendor autoload not found at "%s". Run "composer install".', $autoload)
    );
}

require $autoload;

date_default_timezone_set('Asia/Shanghai');
