<?php declare(strict_types=1);

namespace Bhp\Runtime;

use Bhp\Config\Config;
use Bhp\Device\Device;
use Bhp\Env\Env;
use Bhp\FilterWords\FilterWords;
use Bhp\Profile\ProfileContext;

final class RuntimeContext extends AppContext
{
    public function __construct(
        ?ProfileContext $profileContext = null,
        ?Config $configService = null,
        ?Device $deviceService = null,
        ?FilterWords $filterWordsService = null,
        ?Env $envService = null,
    ) {
        parent::__construct($profileContext, $configService, $deviceService, $filterWordsService, $envService);
    }
}
