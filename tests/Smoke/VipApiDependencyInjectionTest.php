<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class VipApiDependencyInjectionTest extends TestCase
{
    public function testVipApisUseInjectedRequestServiceAndVipPrivilegePluginDoesNotUseStaticFacade(): void
    {
        foreach ([
            'src/Api/Vip/ApiVipCenter.php',
            'src/Api/Vip/ApiPrivilegeAssets.php',
            'src/Api/Vip/ApiExperience.php',
        ] as $path) {
            $contents = $this->readSource($path);
            self::assertStringContainsString('private readonly Request $request', $contents, $path);
            self::assertStringNotContainsString('public static function', $contents, $path);
            self::assertStringNotContainsString('Request::', $contents, $path);
        }

        $plugin = $this->readSource('plugins/VipPrivilege/src/VipPrivilegePlugin.php');
        foreach ([
            'new ApiVipCenter($this->appContext()->request())',
            'new ApiPrivilegeAssets($this->appContext()->request())',
            'new ApiExperience($this->appContext()->request())',
        ] as $snippet) {
            self::assertStringContainsString($snippet, $plugin);
        }
        self::assertStringNotContainsString('ApiVipCenter::', $plugin);
        self::assertStringNotContainsString('ApiPrivilegeAssets::', $plugin);
        self::assertStringNotContainsString('ApiExperience::', $plugin);
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
