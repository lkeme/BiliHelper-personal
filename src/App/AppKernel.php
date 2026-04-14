<?php declare(strict_types=1);

namespace Bhp\App;

use Bhp\Bootstrap\Bootstrap;
use Bhp\Bootstrap\StartupSelfCheck;
use Bhp\Cache\Cache;
use Bhp\Config\Config;
use Bhp\Console\Console;
use Bhp\Console\Command\AppCommand;
use Bhp\Console\Command\DebugCommand;
use Bhp\Console\Command\ScriptCommand;
use Bhp\Core\Core;
use Bhp\Device\Device;
use Bhp\Env\Env;
use Bhp\FilterWords\FilterWords;
use Bhp\Http\HttpClient;
use Bhp\Http\HttpClientInterceptorRegistry;
use Bhp\Http\HttpRequestTrafficMonitor;
use Bhp\Http\BurstRequest;
use Bhp\Http\RaceRequest;
use Bhp\Http\HttpRequestAuditInterceptorProvider;
use Bhp\Http\HttpRequestGovernanceInterceptorProvider;
use Bhp\Http\HttpRequestHeaderPolicyInterceptorProvider;
use Bhp\Http\HttpRequestLogInterceptorProvider;
use Bhp\Http\HttpRequestMetadataInterceptorProvider;
use Bhp\Http\HttpRequestTrafficInterceptorProvider;
use Bhp\Log\Log;
use Bhp\Login\LoginGateStateService;
use Bhp\Login\LoginManualInterventionPolicy;
use Bhp\Login\LoginPendingFlowStore;
use Bhp\Notice\Notice;
use Bhp\Plugin\CorePluginRegistry;
use Bhp\Plugin\ExternalPluginRegistry;
use Bhp\Plugin\Plugin;
use Bhp\Profile\ProfileCacheResetService;
use Bhp\Profile\ProfileContext;
use Bhp\Profile\ProfileInspector;
use Bhp\Request\Request;
use Bhp\Request\RequestRetryPolicy;
use Bhp\Runtime\AppContext;
use Bhp\Runtime\Runtime;
use Bhp\Runtime\RuntimeContext;
use Bhp\Scheduler\Scheduler;
use Bhp\Scheduler\SchedulerStateStore;
use Bhp\WbiSign\WbiSign;

final class AppKernel
{
    private ?BootstrapResult $bootResult = null;

    /**
     * @param string[] $argv
     */
    public function __construct(
        private readonly string $appRoot,
        private readonly array $argv,
    ) {
    }

    /**
     * 处理boot
     * @return BootstrapResult
     */
    public function boot(): BootstrapResult
    {
        if ($this->bootResult instanceof BootstrapResult) {
            return $this->bootResult;
        }

        $readOnlyRequest = Console::isReadOnlyRequest($this->argv);
        $profileName = Console::parse($this->argv);
        $runtimeMode = Console::resolveMode($this->argv);
        $profileContext = ProfileContext::fromAppRoot($this->appRoot, $profileName);
        $container = new ServiceContainer();

        $container->setInstance(ServiceContainer::class, $container);
        $container->setInstance(ProfileContext::class, $profileContext);
        $container->set(Core::class, static fn (ServiceContainer $services): Core => new Core($profileContext));
        $container->set(Config::class, static fn (ServiceContainer $services): Config => new Config($profileContext));
        $container->set(Cache::class, static fn (ServiceContainer $services): Cache => new Cache($profileContext));
        $container->set(Log::class, static fn (ServiceContainer $services): Log => new Log($services->get(AppContext::class)));
        $container->set(Env::class, static fn (ServiceContainer $services): Env => new Env(
            $profileContext,
            $services->get(Log::class),
            'version.json',
            $readOnlyRequest,
        ));
        $container->set(Device::class, static fn (ServiceContainer $services): Device => new Device($profileContext));
        $container->set(FilterWords::class, static fn (ServiceContainer $services): FilterWords => new FilterWords($profileContext));
        $container->set(LoginPendingFlowStore::class, static fn (ServiceContainer $services): LoginPendingFlowStore => new LoginPendingFlowStore(
            $services->get(Cache::class),
        ));
        $container->set(SchedulerStateStore::class, static fn (ServiceContainer $services): SchedulerStateStore => new SchedulerStateStore(
            $services->get(Cache::class),
        ));
        $container->set(ProfileInspector::class, static fn (ServiceContainer $services): ProfileInspector => new ProfileInspector());
        $container->set(HttpRequestTrafficMonitor::class, static fn (ServiceContainer $services): HttpRequestTrafficMonitor => new HttpRequestTrafficMonitor());
        $container->set(AppContext::class, static fn (ServiceContainer $services): AppContext => new RuntimeContext($profileContext, $services));
        $container->set(Runtime::class, static fn (ServiceContainer $services): Runtime => new Runtime(
            $services,
            $services->get(AppContext::class)
        ));
        $container->set(HttpClientInterceptorRegistry::class, static fn (ServiceContainer $services): HttpClientInterceptorRegistry => new HttpClientInterceptorRegistry([
            new HttpRequestMetadataInterceptorProvider(),
            new HttpRequestHeaderPolicyInterceptorProvider(),
            new HttpRequestAuditInterceptorProvider(),
            new HttpRequestTrafficInterceptorProvider($services->get(HttpRequestTrafficMonitor::class)),
            new HttpRequestGovernanceInterceptorProvider(
                $services->get(AppContext::class),
                $services->get(HttpRequestTrafficMonitor::class),
            ),
            new HttpRequestLogInterceptorProvider($services->get(Log::class)),
        ]));
        $container->set(HttpClient::class, static fn (ServiceContainer $services): HttpClient => new HttpClient(
            $services->get(AppContext::class),
            $services->get(HttpClientInterceptorRegistry::class),
        ));
        $container->set(BurstRequest::class, static fn (ServiceContainer $services): BurstRequest => new BurstRequest(
            $services->get(HttpClient::class),
        ));
        $container->set(RaceRequest::class, static fn (ServiceContainer $services): RaceRequest => new RaceRequest(
            $services->get(HttpClient::class),
        ));
        $container->set(Request::class, static fn (ServiceContainer $services): Request => new Request(
            $services->get(HttpClient::class),
            $services->get(AppContext::class),
            $services->get(Cache::class),
            new RequestRetryPolicy(),
        ));
        $container->set(\Bhp\Api\Vip\ApiUser::class, static fn (ServiceContainer $services): \Bhp\Api\Vip\ApiUser => new \Bhp\Api\Vip\ApiUser(
            $services->get(Request::class),
        ));
        $container->set(ProfileCacheResetService::class, static fn (ServiceContainer $services): ProfileCacheResetService => new ProfileCacheResetService(
            $services->get(AppContext::class),
            $services->get(Cache::class),
        ));
        $container->set(LoginGateStateService::class, static fn (ServiceContainer $services): LoginGateStateService => new LoginGateStateService(
            $services->get(AppContext::class),
            $services->get(LoginPendingFlowStore::class),
        ));
        $container->set(LoginManualInterventionPolicy::class, static fn (ServiceContainer $services): LoginManualInterventionPolicy => new LoginManualInterventionPolicy(
            $services->get(AppContext::class),
            $services->get(LoginPendingFlowStore::class),
            $services->get(Notice::class),
        ));
        $container->set(StartupSelfCheck::class, static fn (ServiceContainer $services): StartupSelfCheck => new StartupSelfCheck(
            $services->get(AppContext::class),
            $services->get(ProfileInspector::class),
            $services->get(Plugin::class),
            $services->get(LoginGateStateService::class),
            $services->get(HttpRequestTrafficMonitor::class),
        ));
        $container->set(Notice::class, static fn (ServiceContainer $services): Notice => new Notice(
            $services->get(AppContext::class),
            $services->get(FilterWords::class),
        ));
        $container->set(CorePluginRegistry::class, static fn (ServiceContainer $services): CorePluginRegistry => new CorePluginRegistry());
        $container->set(ExternalPluginRegistry::class, static fn (ServiceContainer $services): ExternalPluginRegistry => new ExternalPluginRegistry());
        $container->set(Plugin::class, static fn (ServiceContainer $services): Plugin => new Plugin(
            $services->get(Config::class),
            $runtimeMode,
            $profileContext->appRoot(),
            $services->get(AppContext::class),
            $services->get(Notice::class),
            $services->get(Log::class),
            $services->get(CorePluginRegistry::class),
            $services->get(ExternalPluginRegistry::class),
        ));
        $container->set(Scheduler::class, static fn (ServiceContainer $services): Scheduler => new Scheduler(
            $services->get(Plugin::class),
            $services->get(Log::class),
            $services->get(LoginGateStateService::class),
            $services->get(LoginManualInterventionPolicy::class),
            $services->get(HttpRequestTrafficMonitor::class),
            $services->get(SchedulerStateStore::class),
        ));
        $container->set(AppCommand::class, static fn (ServiceContainer $services): AppCommand => new AppCommand(
            $services->get(Log::class),
            static fn (): Scheduler => $services->get(Scheduler::class),
            static fn (): Plugin => $services->get(Plugin::class),
            static fn (): ProfileCacheResetService => $services->get(ProfileCacheResetService::class),
        ));
        $container->set(DebugCommand::class, static fn (ServiceContainer $services): DebugCommand => new DebugCommand(
            $services->get(Log::class),
            static fn (): Scheduler => $services->get(Scheduler::class),
            static fn (): Plugin => $services->get(Plugin::class),
            static fn (): ProfileCacheResetService => $services->get(ProfileCacheResetService::class),
        ));
        $container->set(ScriptCommand::class, fn (ServiceContainer $services): ScriptCommand => new ScriptCommand(
            $services->get(Log::class),
            $this->argv,
            $profileContext->appRoot(),
            static fn (): Plugin => $services->get(Plugin::class),
            static fn (): ProfileCacheResetService => $services->get(ProfileCacheResetService::class)
        ));
        $container->set(Console::class, fn (ServiceContainer $services): Console => new Console(
            $this->argv,
            $services->get(Env::class),
            $services->get(AppCommand::class),
            $services->get(DebugCommand::class),
            $services->get(ScriptCommand::class),
        ));

        (new Bootstrap($container, $readOnlyRequest))->boot();
        if (!$readOnlyRequest) {
            WbiSign::bootstrap($container->get(\Bhp\Api\Vip\ApiUser::class));
        }

        $console = $container->get(Console::class);

        return $this->bootResult = new BootstrapResult(
            $container,
            $container->get(AppContext::class),
            $console,
            $console->mode(),
        );
    }
}
