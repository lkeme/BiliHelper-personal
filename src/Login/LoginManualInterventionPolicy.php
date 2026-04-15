<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Runtime\AppContext;

final class LoginManualInterventionPolicy
{
    public function __construct(
        private readonly AppContext $context,
        private readonly LoginPendingFlowStore $pendingFlowStore,
    ) {
    }

    /**
     * 处理enforce
     * @return void
     */
    public function enforce(): void
    {
        $flow = $this->pendingFlowStore->load();
        if (!is_array($flow)) {
            return;
        }

        $type = trim((string)($flow['type'] ?? 'unknown'));
        $this->pendingFlowStore->clear();
        $this->logPending(sprintf(
            '检测到历史登录挂起状态 type=%s，已自动清理，后续将重新开始完整登录流程',
            $type,
        ));
    }

    /**
     * 记录等待人工介入日志
     * @param string $message
     * @return void
     */
    private function logPending(string $message): void
    {
        $this->context->log()->recordInfo($message);
    }
}
