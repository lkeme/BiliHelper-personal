<?php declare(strict_types=1);

namespace Bhp\Login;

final class LoginRuntimeState
{
    public function __construct(
        private string $username = '',
        private string $password = '',
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
}
