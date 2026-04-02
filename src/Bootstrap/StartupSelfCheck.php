<?php declare(strict_types=1);

namespace Bhp\Bootstrap;

use Bhp\Profile\ProfileInspector;
use Bhp\Runtime\Runtime;
use Bhp\Util\AppTerminator;

final class StartupSelfCheck
{
    public function run(): void
    {
        $profile = Runtime::getInstance()->appContext()->profileContext();
        $result = (new ProfileInspector())->inspect($profile);
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
}
