<?php declare(strict_types=1);

namespace Bhp\Plugin;

final class CorePluginRegistry
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(string $appRoot): array
    {
        $normalizedAppRoot = rtrim(str_replace('\\', '/', $appRoot), '/') . '/';

        return [
            [
                'hook' => 'Login',
                'name' => 'Login',
                'class_name' => \Bhp\Login\Login::class,
                'path' => $normalizedAppRoot . 'src/Login/Login.php',
                'source' => 'core',
                'vendor' => 'core',
                'manifest' => [
                    'hook' => 'Login',
                    'name' => 'Login',
                    'version' => '0.0.1',
                    'desc' => '登录',
                    'author' => 'Lkeme',
                    'priority' => 1000,
                    'cycle' => '2(小时)',
                    'valid_until' => '2099-12-31 23:59:59',
                    'interval_seconds' => 7200,
                    'max_concurrency' => 1,
                    'overrun_policy' => 'serialize',
                    'timeout_seconds' => 180.0,
                    'bootstrap_first' => true,
                    'class_name' => \Bhp\Login\Login::class,
                    'source' => 'core',
                    'vendor' => 'core',
                ],
                'manifest_error' => '',
            ],
        ];
    }
}
