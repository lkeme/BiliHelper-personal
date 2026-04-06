<?php declare(strict_types=1);

namespace Bhp\Request;

use Amp\CancelledException as AmpCancelledException;
use Amp\Dns\DnsTimeoutException;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\TimeoutException as HttpTimeoutException;
use Amp\TimeoutException as AmpTimeoutException;
use Bhp\Util\Exceptions\RequestException;
use Bhp\Util\Exceptions\ResponseEmptyException;
use Exception;
use Throwable;

final class RequestFailureClassifier
{
    public function classifyThrowable(Throwable $throwable): string
    {
        if ($throwable instanceof Exception) {
            return $this->classify($throwable);
        }

        return $this->classifyMessage($throwable->getMessage());
    }

    public function classify(Exception $exception): string
    {
        return match (true) {
            $exception instanceof RequestException => $exception->getCategory(),
            $exception instanceof ResponseEmptyException => RequestException::CATEGORY_EMPTY_RESPONSE,
            $exception instanceof HttpTimeoutException,
            $exception instanceof AmpTimeoutException,
            $exception instanceof DnsTimeoutException => RequestException::CATEGORY_TIMEOUT,
            $exception instanceof AmpCancelledException => RequestException::CATEGORY_CANCELLED,
            $exception instanceof HttpException => RequestException::CATEGORY_NETWORK,
            default => $this->classifyMessage($exception->getMessage()),
        };
    }

    public function classifyMessage(string $message): string
    {
        $normalized = strtolower($message);
        if (str_contains($normalized, 'timeout') || str_contains($normalized, 'timed out')) {
            return RequestException::CATEGORY_TIMEOUT;
        }

        if (str_contains($normalized, 'cancel')) {
            return RequestException::CATEGORY_CANCELLED;
        }

        foreach (['network', 'socket', 'connect', 'dns', 'tls', 'ssl'] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return RequestException::CATEGORY_NETWORK;
            }
        }

        return RequestException::CATEGORY_UNKNOWN;
    }
}
