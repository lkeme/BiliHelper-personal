<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\Plugin\Plugin;
use Bhp\Scheduler\Scheduler;
use Bhp\Login\LoginGateStateService;
use Bhp\Login\LoginManualInterventionPolicy;
use Bhp\Login\LoginPendingFlowStore;
use Bhp\Http\HttpRequestTrafficMonitor;
use Bhp\Runtime\AppContext;
use Bhp\Cache\Cache;
use Bhp\Log\Log;
use Bhp\Profile\ProfileContext;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SchedulerValidationTest extends TestCase
{
    public function testRegisterPluginsRejectsDefinitionsMissingRequiredSchedulerMetadata(): void
    {
        $context = $this->createStub(AppContext::class);
        $profileContext = ProfileContext::fromAppRoot(dirname(__DIR__, 2), 'scheduler-validation-test');
        foreach ([$profileContext->rootPath(), $profileContext->configPath(), $profileContext->logPath(), $profileContext->cachePath()] as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
        $cache = new Cache($profileContext);
        $store = new LoginPendingFlowStore($cache);
        $scheduler = new Scheduler(
            $this->createStub(Plugin::class),
            $this->createStub(Log::class),
            new LoginGateStateService($context, $store),
            new LoginManualInterventionPolicy($context, $store),
            new HttpRequestTrafficMonitor(),
            new \Bhp\Scheduler\SchedulerStateStore($cache),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('hook');

        $scheduler->registerPlugins([
            [
                'name' => 'Broken plugin',
            ],
        ]);
    }
}
