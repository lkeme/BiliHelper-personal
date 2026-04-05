<?php declare(strict_types=1);

namespace Bhp\Plugin;

final class CorePluginRegistry
{
    /**
     * @return array<int, array{hook: string, name: string, class_name: string, path: string, source: string}>
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
            ],
        ];
    }
}
