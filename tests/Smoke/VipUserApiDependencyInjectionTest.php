<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class VipUserApiDependencyInjectionTest extends TestCase
{
    public function testApiUserUsesInjectedRequestServiceAndConsumersDoNotUseStaticFacade(): void
    {
        $apiUser = $this->readSource('src/Api/Vip/ApiUser.php');
        $userProfileService = $this->readSource('src/User/UserProfileService.php');
        $wbiSign = $this->readSource('src/WbiSign/WbiSign.php');

        self::assertStringContainsString('private readonly Request $request', $apiUser);
        self::assertStringNotContainsString('public static function', $apiUser);
        self::assertStringNotContainsString('Request::', $apiUser);

        self::assertStringContainsString('private readonly ApiUser $apiUser', $userProfileService);
        self::assertStringNotContainsString('ApiUser::', $userProfileService);
        self::assertStringContainsString('protected static ?ApiUser $apiUser = null;', $wbiSign);
        self::assertStringContainsString('public static function bootstrap(ApiUser $apiUser): void', $wbiSign);
        self::assertStringNotContainsString('ApiUser::', $wbiSign);
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
