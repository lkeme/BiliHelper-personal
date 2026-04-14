<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Login\LoginPendingFlowStore;
use Bhp\Login\LoginRuntimeState;

final class LoginPendingFlowStateService
{
    /**
     * 初始化 LoginPendingFlowStateService
     * @param LoginPendingFlowStore $store
     */
    public function __construct(
        private readonly LoginPendingFlowStore $store,
    ) {
    }

    /**
     * @param array<string, mixed> $flow
     */
    public function set(LoginRuntimeState $state, array $flow): void
    {
        $state->setPendingFlow($flow);
        $this->store->save($flow);
    }

    /**
     * 清空数据
     * @param LoginRuntimeState $state
     * @return void
     */
    public function clear(LoginRuntimeState $state): void
    {
        $state->clearPendingFlow();
        $this->store->clear();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function current(LoginRuntimeState $state): ?array
    {
        if ($state->hasPendingFlow()) {
            return $state->pendingFlow();
        }

        $flow = $this->store->load();
        if (is_array($flow)) {
            $state->setPendingFlow($flow);
        }

        return $flow;
    }
}
