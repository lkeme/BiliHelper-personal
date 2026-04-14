<?php declare(strict_types=1);

namespace Bhp\Request;

use Bhp\Http\RequestOptions;
use Bhp\Util\Exceptions\RequestException;

final class RequestRetryPolicy
{
    /**
     * 初始化 RequestRetryPolicy
     * @param int $maxAttempts
     * @param float $baseDelaySeconds
     * @param float $maxDelaySeconds
     * @param float $totalBudgetSeconds
     * @param float $jitterRatio
     */
    public function __construct(
        private readonly int $maxAttempts = 5,
        private readonly float $baseDelaySeconds = 0.25,
        private readonly float $maxDelaySeconds = 3.0,
        private readonly float $totalBudgetSeconds = 10.0,
        private readonly float $jitterRatio = 0.2,
    ) {
    }

    /**
     * 判断重试是否满足条件
     * @param string $method
     * @param RequestOptions $options
     * @param string $category
     * @param int $attempt
     * @param float $elapsedSeconds
     * @return bool
     */
    public function shouldRetry(
        string $method,
        RequestOptions $options,
        string $category,
        int $attempt,
        float $elapsedSeconds,
    ): bool {
        if ($attempt >= $this->maxAttempts) {
            return false;
        }

        if (!$this->supportsRetry($method, $options)) {
            return false;
        }

        if (!$this->isRetriableCategory($category)) {
            return false;
        }

        return ($elapsedSeconds + $this->delaySecondsForAttempt($attempt)) <= $this->totalBudgetSeconds;
    }

    /**
     * 删除或清理aySecondsForAttempt
     * @param int $attempt
     * @return float
     */
    public function delaySecondsForAttempt(int $attempt): float
    {
        $attempt = max(1, $attempt);
        $delay = min($this->maxDelaySeconds, $this->baseDelaySeconds * (2 ** ($attempt - 1)));
        if ($this->jitterRatio <= 0.0) {
            return $delay;
        }

        $spread = $delay * $this->jitterRatio;
        $min = max(0.0, $delay - $spread);
        $max = $delay + $spread;

        return $min + (($max - $min) * (mt_rand() / mt_getrandmax()));
    }

    /**
     * 处理maxAttempts
     * @return int
     */
    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * 处理base延迟Seconds
     * @return float
     */
    public function baseDelaySeconds(): float
    {
        return $this->baseDelaySeconds;
    }

    /**
     * 处理max延迟Seconds
     * @return float
     */
    public function maxDelaySeconds(): float
    {
        return $this->maxDelaySeconds;
    }

    /**
     * 处理totalBudgetSeconds
     * @return float
     */
    public function totalBudgetSeconds(): float
    {
        return $this->totalBudgetSeconds;
    }

    /**
     * 处理jitterRatio
     * @return float
     */
    public function jitterRatio(): float
    {
        return $this->jitterRatio;
    }

    /**
     * 处理supports重试
     * @param string $method
     * @param RequestOptions $options
     * @return bool
     */
    private function supportsRetry(string $method, RequestOptions $options): bool
    {
        return in_array(strtolower($method), ['get', 'head', 'options'], true) || $options->retryable;
    }

    /**
     * 判断RetriableCategory是否满足条件
     * @param string $category
     * @return bool
     */
    private function isRetriableCategory(string $category): bool
    {
        return in_array($category, [
            RequestException::CATEGORY_TIMEOUT,
            RequestException::CATEGORY_CANCELLED,
            RequestException::CATEGORY_EMPTY_RESPONSE,
            RequestException::CATEGORY_NETWORK,
        ], true);
    }
}
