<?php declare(strict_types=1);

namespace Bhp\Api\Support;

final class RawTextNormalizer implements ApiResponseNormalizerInterface
{
    public function normalize(mixed $payload, string $label): mixed
    {
        return is_string($payload) ? $payload : '';
    }
}
