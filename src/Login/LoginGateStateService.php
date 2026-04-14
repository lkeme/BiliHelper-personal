<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Runtime\AppContext;

final class LoginGateStateService
{
    /**
     * @var string[]
     */
    private const REQUIRED_AUTH_KEYS = [
        'access_token',
        'refresh_token',
        'cookie',
        'uid',
        'csrf',
    ];

    /**
     * 初始化 LoginGateStateService
     * @param AppContext $context
     * @param LoginPendingFlowStore $pendingFlowStore
     */
    public function __construct(
        private readonly AppContext $context,
        private readonly LoginPendingFlowStore $pendingFlowStore,
    ) {
    }

    /**
     * 处理认证Ready
     * @return bool
     */
    public function authReady(): bool
    {
        foreach (self::REQUIRED_AUTH_KEYS as $key) {
            if (trim($this->context->auth($key)) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * 判断待处理流程是否满足条件
     * @return bool
     */
    public function hasPendingFlow(): bool
    {
        return $this->pendingFlowStore->load() !== null;
    }

    /**
     * 判断BlockBusinessTasks是否满足条件
     * @return bool
     */
    public function shouldBlockBusinessTasks(): bool
    {
        return $this->hasPendingFlow() || !$this->authReady();
    }

    /**
     * 处理状态
     * @return string
     */
    public function state(): string
    {
        if ($this->hasPendingFlow()) {
            return 'pending_manual_intervention';
        }

        return $this->authReady() ? 'auth_ready' : 'missing_auth';
    }
}
