<?php declare(strict_types=1);

namespace Bhp\Runtime;

use Bhp\Config\Config;
use Bhp\Device\Device;
use Bhp\Env\Env;
use Bhp\FilterWords\FilterWords;
use Bhp\Profile\ProfileContext;
use Bhp\Util\DesignPattern\SingleTon;

class Runtime extends SingleTon
{
    private AppContext $context;

    public function init(
        ?ProfileContext $profileContext = null,
        ?Config $configService = null,
        ?Device $deviceService = null,
        ?FilterWords $filterWordsService = null,
        ?Env $envService = null,
    ): void
    {
        $this->context = new RuntimeContext(
            $profileContext ?? ProfileContext::fromRuntimeConstants($this->resolveAppRoot(), $this->resolveProfileName()),
            $configService,
            $deviceService,
            $filterWordsService,
            $envService,
        );
    }

    public function context(): AppContext
    {
        return $this->context;
    }

    public function appContext(): AppContext
    {
        return $this->context;
    }

    protected function resolveAppRoot(): string
    {
        if (defined('APP_RESOURCES_PATH')) {
            return dirname(rtrim((string)APP_RESOURCES_PATH, "\\/"));
        }

        return getcwd() ?: '';
    }

    protected function resolveProfileName(): string
    {
        if (defined('PROFILE_CONFIG_PATH')) {
            return basename(dirname(rtrim((string)PROFILE_CONFIG_PATH, "\\/")));
        }

        return 'user';
    }
}
