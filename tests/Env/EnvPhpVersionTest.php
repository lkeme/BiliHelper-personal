<?php declare(strict_types=1);

namespace Tests\Env;

use Bhp\Console\Cli\RuntimeException;
use Bhp\Env\Env;
use Bhp\Log\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

require_once dirname(__DIR__, 2) . '/src/Console/Cli/RuntimeException.php';
require_once dirname(__DIR__, 2) . '/src/Util/Resource/BaseResource.php';
require_once dirname(__DIR__, 2) . '/src/Log/Log.php';
require_once dirname(__DIR__, 2) . '/src/Env/Env.php';

final class EnvPhpVersionTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function phpVersions(): iterable
    {
        yield 'older PHP branch' => ['8.4.27', false];
        yield 'initial PHP 8.5 release' => ['8.5.0', false];
        yield 'last vulnerable PHP 8.5 release' => ['8.5.11', false];
        yield 'prerelease of the fixed version' => ['8.5.12RC1', false];
        yield 'first fixed PHP 8.5 release' => ['8.5.12', true];
        yield 'later PHP 8.5 release' => ['8.5.13', true];
        yield 'later PHP branch' => ['8.6.0', true];
    }

    #[DataProvider('phpVersions')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRuntimeVersionRequirement(string $version, bool $supported): void
    {
        // Namespace fallback lets each isolated process exercise the real check
        // without requiring multiple PHP installations or handling web requests.
        define('Bhp\\Env\\PHP_VERSION', $version);

        $env = (new ReflectionClass(Env::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Env::class, 'log'))->setValue($env, $this->createStub(Log::class));
        $env->app_name = 'BiliHelper-personal';
        $env->app_branch = 'test';
        $env->app_version = 'test';
        $env->app_source = 'https://github.com/lkeme/BiliHelper-personal';

        if (!$supported) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Please upgrade PHP version >= 8.5.12');
        }

        $result = (new ReflectionMethod(Env::class, 'inspectConfigure'))->invoke($env);

        if ($supported) {
            $this->assertSame($env, $result);
        }
    }
}
