<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\App\ServiceContainer;
use Bhp\Config\Config;
use Bhp\FilterWords\FilterWords;
use Bhp\Log\Log;
use Bhp\Notice\Notice;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\CorePluginRegistry;
use Bhp\Plugin\ExternalPluginRegistry;
use Bhp\Plugin\Plugin;
use Bhp\Profile\ProfileContext;
use Bhp\Runtime\AppContext;
use Bhp\Runtime\Runtime;
use Bhp\Runtime\RuntimeContext;
use PHPUnit\Framework\TestCase;

final class PluginRegistryTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function expectedOfficialHooks(): array
    {
        return [
            'ActivityInfoUpdate',
            'ActivityLottery',
            'AwardRecords',
            'BatchUnfollow',
            'BpConsumption',
            'CheckUpdate',
            'DailyGold',
            'GameForecast',
            'Judge',
            'LiveGoldBox',
            'LiveReservation',
            'Lottery',
            'LoveClub',
            'MainSite',
            'Manga',
            'PolishMedal',
            'Silver2Coin',
            'VipPoint',
            'VipPrivilege',
        ];
    }

    private function appRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array<int, array{hook: string, name: string, class_name: string, path: string, source: string, vendor: string}>
     */
    private function externalEntries(): array
    {
        return (new ExternalPluginRegistry())->all($this->appRoot());
    }

    public function testExternalRegistryEnumeratesEveryBundledThirdPartyPlugin(): void
    {
        $entries = $this->externalEntries();
        $hooks = array_column($entries, 'hook');

        self::assertSame($this->expectedOfficialHooks(), $hooks);
        self::assertNotContains('Login', $hooks);
        self::assertCount(count(array_unique($hooks)), $hooks);

        foreach ($entries as $entry) {
            self::assertSame('official', $entry['vendor']);
            self::assertSame('bundled', $entry['source']);
            self::assertFileExists($entry['path'], 'Plugin entry file must exist for ' . $entry['hook']);
            self::assertFileExists($this->appRoot() . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $entry['hook'] . DIRECTORY_SEPARATOR . 'plugin.json');
        }
    }

    public function testCoreRegistryContainsOnlyLoginPlugin(): void
    {
        $entries = (new CorePluginRegistry())->all($this->appRoot());

        self::assertCount(1, $entries);
        self::assertSame('Login', $entries[0]['hook']);
        self::assertSame(\Bhp\Login\Login::class, $entries[0]['class_name']);
        self::assertSame('core', $entries[0]['source']);
        self::assertFileExists($entries[0]['path']);
    }

    public function testPluginRuntimeUsesCoreAndExternalRegistriesInsteadOfBuiltinRegistry(): void
    {
        $contents = file_get_contents($this->appRoot() . DIRECTORY_SEPARATOR . 'src/Plugin/Plugin.php');

        self::assertIsString($contents);
        self::assertStringContainsString('CorePluginRegistry', $contents);
        self::assertStringContainsString('ExternalPluginRegistry', $contents);
        self::assertStringNotContainsString('BuiltinPluginRegistry', $contents);
        self::assertStringNotContainsString('PluginDiscovery', $contents);
        self::assertStringNotContainsString('PluginClassNameResolver', $contents);
    }

    public function testExternalRegistrySourceReadsManifestFilesFromPluginsDirectory(): void
    {
        $contents = file_get_contents($this->appRoot() . DIRECTORY_SEPARATOR . 'src/Plugin/ExternalPluginRegistry.php');

        self::assertIsString($contents);
        self::assertStringContainsString("glob(\$pluginsRoot . '/*/plugin.json')", $contents);
        self::assertStringContainsString("'vendor' => \$vendor !== '' ? \$vendor : 'unknown'", $contents);
    }

    public function testAppRuntimeExcludesScriptPlugins(): void
    {
        $context = $this->createStub(AppContext::class);
        $plugin = new TestPluginRuntime(
            $this->createStub(Config::class),
            'app',
            __DIR__,
            $context,
            $this->notice($context),
            $this->createStub(Log::class),
            null,
            null,
        );

        self::assertTrue($plugin->hasPlugin('FakeAppModePlugin'));
        self::assertFalse($plugin->hasPlugin('FakeScriptModePlugin'));
    }

    public function testDebugRuntimeExcludesScriptPlugins(): void
    {
        $context = $this->createStub(AppContext::class);
        $plugin = new TestPluginRuntime(
            $this->createStub(Config::class),
            'debug',
            __DIR__,
            $context,
            $this->notice($context),
            $this->createStub(Log::class),
            null,
            null,
        );

        self::assertTrue($plugin->hasPlugin('FakeAppModePlugin'));
        self::assertFalse($plugin->hasPlugin('FakeScriptModePlugin'));
    }

    public function testScriptRuntimeExcludesAppPlugins(): void
    {
        $context = $this->createStub(AppContext::class);
        $plugin = new TestPluginRuntime(
            $this->createStub(Config::class),
            'script',
            __DIR__,
            $context,
            $this->notice($context),
            $this->createStub(Log::class),
            null,
            null,
        );

        self::assertFalse($plugin->hasPlugin('FakeAppModePlugin'));
        self::assertTrue($plugin->hasPlugin('FakeScriptModePlugin'));
    }

    public function testMissingClassFailureRetainsRegistryMetadata(): void
    {
        FailureModePluginRuntime::$activePlugins = [
            [
                'hook' => 'MissingClassHook',
                'name' => 'MissingClassHook',
                'class_name' => 'MissingClassForPluginRegistryTest',
                'path' => __FILE__,
            ],
        ];

        $context = $this->createStub(AppContext::class);
        $plugin = new FailureModePluginRuntime(
            $this->createStub(Config::class),
            'app',
            __DIR__,
            $context,
            $this->notice($context),
            $this->createStub(Log::class),
            null,
            null,
        );
        $entry = $plugin->registry()['MissingClassHook'] ?? null;

        self::assertSame([
            'hook' => 'MissingClassHook',
            'name' => 'MissingClassHook',
            'class_name' => 'MissingClassForPluginRegistryTest',
            'path' => __FILE__,
            'status' => 'failed',
            'error' => '插件类不存在',
        ], $entry);
    }

    public function testInvalidManifestFailureRetainsRegistryMetadata(): void
    {
        $this->activateTestRuntime();
        FailureModePluginRuntime::$activePlugins = [
            [
                'hook' => 'BrokenManifestHook',
                'name' => 'BrokenManifestHook',
                'class_name' => BrokenManifestPlugin::class,
                'path' => __FILE__,
            ],
        ];

        $context = $this->createStub(AppContext::class);
        $plugin = new FailureModePluginRuntime(
            $this->createStub(Config::class),
            'app',
            __DIR__,
            $context,
            $this->notice($context),
            $this->createStub(Log::class),
            null,
            null,
        );
        $entry = $plugin->registry()['BrokenManifestHook'] ?? null;

        self::assertSame([
            'hook' => 'BrokenManifestHook',
            'name' => 'BrokenManifestHook',
            'class_name' => BrokenManifestPlugin::class,
            'path' => __FILE__,
            'status' => 'failed',
            'error' => '插件 BrokenManifestHook manifest 缺少关键字段 desc',
        ], $entry);
    }

    public function testLegacyPluginDirectoryIsRemoved(): void
    {
        self::assertDirectoryDoesNotExist($this->appRoot() . DIRECTORY_SEPARATOR . 'plugin');
    }

    private function activateTestRuntime(): void
    {
        $container = new ServiceContainer();
        $config = $this->createStub(Config::class);
        $config
            ->method('get')
            ->willReturnCallback(static fn(string $key, mixed $default = null, string $type = 'default'): mixed => $default);

        $profileContext = ProfileContext::fromAppRoot($this->appRoot(), 'plugin-registry-test');
        $context = new RuntimeContext($profileContext, $container);

        $container->setInstance(Config::class, $config);
        $container->setInstance(AppContext::class, $context);
        $container->setInstance(Log::class, new Log($context));

        Runtime::activate(new Runtime($container, $context));
    }

    private function notice(AppContext $context): Notice
    {
        $filterWords = $this->createStub(FilterWords::class);
        $filterWords
            ->method('get')
            ->willReturn([]);

        return new Notice($context, $filterWords, null, []);
    }
}

final class TestPluginRuntime extends Plugin
{
    /**
     * @return array<int, array{hook: string, name: string, class_name: string, path: string}>
     */
    protected function getActivePlugins(): array
    {
        return [
            [
                'hook' => 'FakeAppModePlugin',
                'name' => 'FakeAppModePlugin',
                'class_name' => FakeAppModePlugin::class,
                'path' => __FILE__,
            ],
            [
                'hook' => 'FakeScriptModePlugin',
                'name' => 'FakeScriptModePlugin',
                'class_name' => FakeScriptModePlugin::class,
                'path' => __FILE__,
            ],
        ];
    }

    protected function preloadPlugins(): void
    {
    }
}

final class FailureModePluginRuntime extends Plugin
{
    /**
     * @var array<int, array{hook: string, name: string, class_name: string, path: string}>
     */
    public static array $activePlugins = [];

    /**
     * @return array<int, array{hook: string, name: string, class_name: string, path: string}>
     */
    protected function getActivePlugins(): array
    {
        return self::$activePlugins;
    }

    protected function preloadPlugins(): void
    {
    }
}

final class FakeAppModePlugin extends BasePlugin
{
    public ?array $info = [
        'hook' => 'FakeAppModePlugin',
        'name' => 'FakeAppModePlugin',
        'version' => '0.0.1',
        'desc' => 'test app plugin',
        'priority' => 1500,
        'cycle' => 'manual',
        'mode' => 'app',
    ];

    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, false);
    }

    public function execute(): void
    {
    }
}

final class FakeScriptModePlugin extends BasePlugin
{
    public ?array $info = [
        'hook' => 'FakeScriptModePlugin',
        'name' => 'FakeScriptModePlugin',
        'version' => '0.0.1',
        'desc' => 'test script plugin',
        'priority' => 1600,
        'cycle' => 'manual',
        'mode' => 'script',
    ];

    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, false);
    }

    public function execute(): void
    {
    }
}

final class BrokenManifestPlugin extends BasePlugin
{
    public ?array $info = [
        'hook' => 'BrokenManifestHook',
        'name' => 'BrokenManifestHook',
        'version' => '0.0.1',
        'priority' => 1700,
        'cycle' => 'manual',
        'mode' => 'app',
    ];

    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, false);
    }

    public function execute(): void
    {
    }
}
