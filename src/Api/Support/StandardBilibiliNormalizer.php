<?php declare(strict_types=1);

namespace Bhp\Api\Support;

final class StandardBilibiliNormalizer implements ApiResponseNormalizerInterface
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $payload, string $label): mixed
    {
        if (!is_array($payload)) {
            return [
                'code' => -500,
                'message' => "{$label} 响应格式异常",
                'data' => [],
            ];
        }

        return $payload;
    }
}
