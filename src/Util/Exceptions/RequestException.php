<?php declare(strict_types=1);

namespace Bhp\Util\Exceptions;

use Exception;
use Throwable;

class RequestException extends Exception
{
    public const CATEGORY_TIMEOUT = 'timeout';
    public const CATEGORY_CANCELLED = 'cancelled';
    public const CATEGORY_EMPTY_RESPONSE = 'empty_response';
    public const CATEGORY_NETWORK = 'network';
    public const CATEGORY_GOVERNED = 'governed';
    public const CATEGORY_UNKNOWN = 'unknown';

    public function __construct(
        string $message = '',
        private readonly string $method = '',
        private readonly string $url = '',
        int $code = 0,
        ?Throwable $previous = null,
        private readonly string $category = self::CATEGORY_UNKNOWN,
        private readonly string $requestId = '',
        private readonly int $retryCount = 0,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }
}
