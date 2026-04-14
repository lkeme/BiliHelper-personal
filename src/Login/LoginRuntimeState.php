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

    /**
     * 设置Credentials
     * @param string $username
     * @param string $password
     * @return void
     */
    public function setCredentials(string $username, string $password): void
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * 处理username
     * @return string
     */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * 处理password
     * @return string
     */
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

    /**
     * 判断待处理流程是否满足条件
     * @return bool
     */
    public function hasPendingFlow(): bool
    {
        return is_array($this->pendingFlow);
    }

    /**
     * 删除或清理待处理流程
     * @return void
     */
    public function clearPendingFlow(): void
    {
        $this->pendingFlow = null;
    }
}
