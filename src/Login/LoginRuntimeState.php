<?php declare(strict_types=1);

namespace Bhp\Login;

final class LoginRuntimeState
{
    /**
     * @param array<string, mixed>|null $pendingFlow
     */
    public function __construct(
        private string $username = '',
        private string $password = '',
        private ?array $pendingFlow = null,
    ) {
    }

    public function setCredentials(string $username, string $password): void
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }

    /**
     * @param array<string, mixed> $flow
     */
    public function setPendingFlow(array $flow): void
    {
        $this->pendingFlow = $flow;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingFlow(): ?array
    {
        return $this->pendingFlow;
    }

    public function hasPendingFlow(): bool
    {
        return is_array($this->pendingFlow);
    }

    public function clearPendingFlow(): void
    {
        $this->pendingFlow = null;
    }
}
