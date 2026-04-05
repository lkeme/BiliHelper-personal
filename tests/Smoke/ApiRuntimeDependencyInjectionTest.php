<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class ApiRuntimeDependencyInjectionTest extends TestCase
{
    public function testApiCaptchaFetchDoesNotReadCaptchaUrlFromRuntime(): void
    {
        $contents = $this->readSource('src/Api/Passport/ApiCaptcha.php');
        $loginCaptchaService = $this->readSource('src/Login/LoginCaptchaService.php');

        self::assertStringNotContainsString('Runtime::appContext()', $contents);
        self::assertStringContainsString('private readonly Request $request', $contents);
        self::assertStringNotContainsString('public static function', $contents);
        self::assertStringContainsString('public function fetch(string $url, string $challenge): array', $contents);
        self::assertStringContainsString('new ApiCaptcha($this->context->request())', $loginCaptchaService);
    }

    public function testApiHeartBeatDoesNotReadHeartbeatUrlFromRuntime(): void
    {
        $contents = $this->readSource('src/Api/XLive/DataInterface/V1/HeartBeat/ApiHeartBeat.php');

        self::assertStringNotContainsString('Runtime::appContext()', $contents);
        self::assertStringContainsString('private readonly Request $request', $contents);
        self::assertStringContainsString('private readonly ApiCalcSign $calcSignApi;', $contents);
        self::assertStringContainsString('$this->calcSignApi->heartBeat($heartbeatUrl, $payload, [3, 7, 2, 6, 8]);', $contents);
    }

    public function testAppApiPayloadBuildersDelegateStatisticsAndAccessTokenToSign(): void
    {
        $request = $this->readSource('src/Request/Request.php');

        self::assertStringContainsString('public static function signCommon(array $payload, bool $includeStatistics = false): array', $request);
        self::assertStringContainsString('public static function signLogin(array $payload): array', $request);
        self::assertStringContainsString("if (\$includeStatistics)", $request);

        foreach ([
            'src/Api/Api/Pgc/Activity/Score/ApiTask.php',
            'src/Api/Api/Pgc/Activity/Deliver/ApiTask.php',
            'src/Api/Show/Api/Activity/Fire/Common/ApiEvent.php',
            'src/Api/XLive/AppUcenter/V1/ApiUserTask.php',
            'src/Api/Video/ApiCoin.php',
        ] as $path) {
            $contents = $this->readSource($path);
            self::assertStringNotContainsString('Runtime::appContext()', $contents, $path);
            self::assertStringNotContainsString('Sign::', $contents, $path);
        }
    }

    public function testJsonContentTypeApisUseJsonBodyTransport(): void
    {
        $event = $this->readSource('src/Api/Show/Api/Activity/Fire/Common/ApiEvent.php');
        self::assertStringContainsString('application/json', $event);
        self::assertStringContainsString('postJsonBodyText(', $event);
        self::assertStringNotContainsString('postText(', $event);

        $score = $this->readSource('src/Api/Api/Pgc/Activity/Score/ApiTask.php');
        self::assertStringContainsString('application/json', $score);
        self::assertStringContainsString("str_contains(strtolower((string)(\$headers['Content-Type'] ?? \$headers['content-type'] ?? '')), 'application/json')", $score);
        self::assertStringContainsString('postJsonBodyText(', $score);
    }

    public function testApiSupportAndLoginApisDoNotUseStaticLogFacade(): void
    {
        foreach ([
            'src/Api/Support/ApiJson.php',
            'src/Api/Passport/ApiOauth2.php',
            'src/Api/XLive/DataInterface/V1/HeartBeat/ApiHeartBeat.php',
        ] as $path) {
            $contents = $this->readSource($path);
            self::assertStringNotContainsString('Log::', $contents, $path);
        }
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
