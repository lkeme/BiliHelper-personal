<?php declare(strict_types=1);

namespace Bhp\Http;

use Amp\Cancellation;

final class RequestOptions
{
    public array $headers = [];
    public array $query = [];
    public ?array $json = null;
    public ?array $formParams = null;
    public ?string $body = null;
    public ?string $proxy = null;
    public ?string $sink = null;
    public bool $followRedirects = true;
    public float $timeout = 30.0;
    public ?Cancellation $cancellation = null;
}
