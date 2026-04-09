<?php declare(strict_types=1);

namespace Bhp\Bootstrap;

use Bhp\App\ServiceContainer;
use Bhp\Cache\Cache;
use Bhp\Console\Console;
use Bhp\Core\Core;
use Bhp\Device\Device;
use Bhp\Env\Env;
use Bhp\FilterWords\FilterWords;
use Bhp\Http\HttpClient;
use Bhp\Log\Log;
use Bhp\Notice\Notice;
use Bhp\Plugin\Plugin;
use Bhp\Profile\ProfileCacheResetService;
use Bhp\Request\Request;
use Bhp\Runtime\Runtime;
use Bhp\Config\Config;
use Bhp\Scheduler\Scheduler;

final class Bootstrap
{
    public function __construct(
        private readonly ServiceContainer $container,
        private readonly bool $readOnlyRequest = false,
    ) {
    }

    public function boot(): void
    {
        if ($this->readOnlyRequest) {
            $this->container->get(Console::class);
            return;
        }

        $this->container->get(Core::class);
        $this->container->get(Runtime::class);
        $this->container->get(Config::class);
        $this->container->get(Cache::class);
        $this->container->get(Log::class);
        $this->container->get(Device::class);
        $this->container->get(FilterWords::class);
        $this->container->get(Console::class);
        $this->container->get(Env::class);

        $this->container->get(HttpClient::class);
        $this->container->get(Request::class);
        $this->container->get(Notice::class);
        $this->container->get(StartupSelfCheck::class)->run();
        $this->container->get(Plugin::class);
        $this->container->get(Scheduler::class);
    }
}
