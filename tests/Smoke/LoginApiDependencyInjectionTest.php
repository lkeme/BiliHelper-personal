<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class LoginApiDependencyInjectionTest extends TestCase
{
    public function testLoginApiClassesUseInjectedRequestService(): void
    {
        foreach ([
            'src/Api/Passport/ApiLogin.php',
            'src/Api/Passport/ApiOauth2.php',
            'src/Api/PassportTv/ApiQrcode.php',
            'src/Api/WWW/ApiMain.php',
        ] as $path) {
            $contents = $this->readSource($path);
            self::assertStringContainsString('private readonly Request $request', $contents, $path);
            self::assertStringNotContainsString('public static function', $contents, $path);
            self::assertStringNotContainsString('Request::', $contents, $path);
        }
    }

    public function testLoginServicesUseInjectedApiClientsInsteadOfStaticApis(): void
    {
        foreach ([
            'src/Login/LoginAuthenticationService.php' => 'private readonly ApiLogin $apiLogin',
            'src/Login/LoginSmsService.php' => 'private readonly ApiLogin $apiLogin',
            'src/Login/LoginCredentialService.php' => 'private readonly ApiOauth2 $apiOauth2',
            'src/Login/LoginQrService.php' => 'private readonly ApiQrcode $apiQrcode',
            'src/Login/LoginTokenLifecycleService.php' => 'private readonly ApiOauth2 $apiOauth2',
        ] as $path => $expected) {
            $contents = $this->readSource($path);
            self::assertStringContainsString($expected, $contents, $path);
            self::assertStringNotContainsString('ApiLogin::', $contents, $path);
            self::assertStringNotContainsString('ApiOauth2::', $contents, $path);
            self::assertStringNotContainsString('ApiQrcode::', $contents, $path);
            self::assertStringNotContainsString('ApiMain::', $contents, $path);
        }
    }

    public function testLoginPluginConstructsApiClientsFromInjectedRequestService(): void
    {
        $contents = $this->readSource('src/Login/Login.php');

        foreach ([
            'new ApiLogin($this->appContext()->request())',
            'new ApiOauth2($this->appContext()->request())',
            'new ApiQrcode($this->appContext()->request())',
            'new ApiMain($this->appContext()->request())',
        ] as $snippet) {
            self::assertStringContainsString($snippet, $contents);
        }

        self::assertStringNotContainsString('ApiLogin::', $contents);
        self::assertStringNotContainsString('ApiOauth2::', $contents);
        self::assertStringNotContainsString('ApiQrcode::', $contents);
        self::assertStringNotContainsString('ApiMain::', $contents);
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
