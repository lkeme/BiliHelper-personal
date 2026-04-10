<?php declare(strict_types=1);

namespace Bhp\Api\Support;

interface ApiResponseNormalizerInterface
{
    public function normalize(mixed $payload, string $label): mixed;
}
