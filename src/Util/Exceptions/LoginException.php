<?php declare(strict_types=1);

namespace Bhp\Util\Exceptions;

use RuntimeException;
use Throwable;

class LoginException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly float $retryAfterSeconds = 3600.0,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRetryAfterSeconds(): float
    {
        return $this->retryAfterSeconds;
    }
}
