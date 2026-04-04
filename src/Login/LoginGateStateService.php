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

    public function __construct(
        private readonly ?AppContext $context = null,
        private readonly ?LoginPendingFlowStore $pendingFlowStore = null,
    ) {
    }

    public function authReady(): bool
    {
        foreach (self::REQUIRED_AUTH_KEYS as $key) {
            if (trim($this->context()->auth($key)) === '') {
                return false;
            }
        }

        return true;
    }

    public function hasPendingFlow(): bool
    {
        return $this->pendingFlowStore()->load() !== null;
    }

    public function shouldBlockBusinessTasks(): bool
    {
        return $this->hasPendingFlow() || !$this->authReady();
    }

    public function state(): string
    {
        if ($this->hasPendingFlow()) {
            return 'pending_manual_intervention';
        }

        return $this->authReady() ? 'auth_ready' : 'missing_auth';
    }

    private function context(): AppContext
    {
        return $this->context ?? new AppContext();
    }

    private function pendingFlowStore(): LoginPendingFlowStore
    {
        return $this->pendingFlowStore ?? new LoginPendingFlowStore();
    }
}
