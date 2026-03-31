<?php declare(strict_types=1);

namespace Bhp\Profile;

use Bhp\Util\Exceptions\BootstrapException;

final class ProfileRegistry
{
    public function __construct(private readonly string $appRoot)
    {
    }

    /**
     * @return array<string, ProfileContext>
     */
    public function discover(): array
    {
        $profiles = [];
        $profileDirectory = $this->profileDirectory();
        if (!is_dir($profileDirectory)) {
            return [];
        }

        foreach (scandir($profileDirectory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            if (!is_dir($profileDirectory . $name)) {
                continue;
            }

            $profiles[$name] = ProfileContext::fromAppRoot($this->appRoot, $name);
        }

        ksort($profiles);

        return $profiles;
    }

    public function resolve(string $name): ProfileContext
    {
        $profiles = $this->discover();
        if (!isset($profiles[$name])) {
            throw new BootstrapException("未找到 profile {$name}，请检查 profile 目录");
        }

        return $profiles[$name];
    }

    private function profileDirectory(): string
    {
        return rtrim(str_replace('\\', '/', $this->appRoot), '/') . '/profile/';
    }
}
