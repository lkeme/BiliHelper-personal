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

    /**
     * 初始化 RequestException
     * @param string $message
     * @param string $method
     * @param string $url
     * @param int $code
     * @param Throwable $previous
     * @param string $category
     * @param string $requestId
     * @param int $retryCount
     */
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

    /**
     * 获取Method
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * 获取URL
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * 获取Category
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * 获取请求Id
     * @return string
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * 获取重试数量
     * @return int
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }
}
