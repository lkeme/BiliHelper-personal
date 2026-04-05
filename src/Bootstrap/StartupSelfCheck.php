<?php declare(strict_types=1);

namespace Bhp\Bootstrap;

use Bhp\Profile\ProfileInspector;
use Bhp\Runtime\AppContext;
use Bhp\Util\AppTerminator;

final class StartupSelfCheck
{
    public function __construct(
        private readonly AppContext $context,
        private readonly ?ProfileInspector $profileInspector = null,
    ) {
    }

    public function run(): void
    {
        $profile = $this->context->profileContext();
        $result = $this->profileInspector()->inspect($profile);
        if ($result->isHealthy()) {
            return;
        }

        $details = [];
        foreach ($result->diagnostics() as $key => $value) {
            if ($key === 'profile' || $key === 'status') {
                continue;
            }

            $details[] = $key . '=' . $value;
        }

        AppTerminator::fail('启动自检失败: ' . implode(', ', $details));
    }

    private function profileInspector(): ProfileInspector
    {
        return $this->profileInspector ?? new ProfileInspector();
    }
}
