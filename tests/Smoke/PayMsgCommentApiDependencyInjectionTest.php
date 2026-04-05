<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class PayMsgCommentApiDependencyInjectionTest extends TestCase
{
    public function testPayMsgCommentApisUseInjectedRequestService(): void
    {
        foreach ([
            'src/Api/Pay/ApiPay.php',
            'src/Api/Msg/ApiMsg.php',
            'src/Api/Comment/ApiReply.php',
        ] as $path) {
            $contents = $this->readSource($path);
            self::assertStringContainsString('private readonly Request $request', $contents, $path);
            self::assertStringNotContainsString('public static function', $contents, $path);
            self::assertStringNotContainsString('Request::', $contents, $path);
        }
    }

    public function testPluginsUseInjectedPayAndMsgApis(): void
    {
        $bp = $this->readSource('plugins/BpConsumption/src/BpConsumptionPlugin.php');
        $medal = $this->readSource('plugins/PolishMedal/src/PolishMedalPlugin.php');

        self::assertStringContainsString('new ApiPay($this->appContext()->request())', $bp);
        self::assertStringNotContainsString('ApiPay::', $bp);
        self::assertStringContainsString('new ApiMsg($this->appContext()->request())', $medal);
        self::assertStringNotContainsString('ApiMsg::', $medal);
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
