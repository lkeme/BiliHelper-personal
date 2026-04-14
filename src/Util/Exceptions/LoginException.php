<?php declare(strict_types=1);

namespace Bhp\Util\Exceptions;

use RuntimeException;
use Throwable;

class LoginException extends RuntimeException
{
    /**
     * 初始化 LoginException
     * @param string $message
     * @param float $retryAfterSeconds
     * @param int $code
     * @param Throwable $previous
     */
    public function __construct(
        string $message,
        private readonly float $retryAfterSeconds = 3600.0,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取重试AfterSeconds
     * @return float
     */
    public function getRetryAfterSeconds(): float
    {
        return $this->retryAfterSeconds;
    }
}
