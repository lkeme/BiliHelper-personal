<?php declare(strict_types=1);

namespace Bhp\Request;

use Bhp\Http\RequestOptions;
use Bhp\Util\Exceptions\RequestException;

final class RequestRetryPolicy
{
    public function __construct(
        private readonly int $maxAttempts = 5,
        private readonly float $baseDelaySeconds = 0.25,
        private readonly float $maxDelaySeconds = 3.0,
        private readonly float $totalBudgetSeconds = 10.0,
        private readonly float $jitterRatio = 0.2,
    ) {
    }

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

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function baseDelaySeconds(): float
    {
        return $this->baseDelaySeconds;
    }

    public function maxDelaySeconds(): float
    {
        return $this->maxDelaySeconds;
    }

    public function totalBudgetSeconds(): float
    {
        return $this->totalBudgetSeconds;
    }

    public function jitterRatio(): float
    {
        return $this->jitterRatio;
    }

    private function supportsRetry(string $method, RequestOptions $options): bool
    {
        return in_array(strtolower($method), ['get', 'head', 'options'], true) || $options->retryable;
    }

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
