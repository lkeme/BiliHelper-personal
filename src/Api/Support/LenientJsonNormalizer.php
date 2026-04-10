<?php declare(strict_types=1);

namespace Bhp\Api\Support;

final class LenientJsonNormalizer implements ApiResponseNormalizerInterface
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

        if (!array_key_exists('message', $payload) && isset($payload['msg']) && is_scalar($payload['msg'])) {
            $payload['message'] = (string)$payload['msg'];
        }

        if (!array_key_exists('code', $payload) && isset($payload['status'])) {
            $status = $payload['status'];
            $payload['code'] = match (true) {
                is_bool($status) => $status ? 0 : -1,
                is_int($status) => $status,
                is_float($status) => (int)$status,
                default => -1,
            };
        }

        return $payload;
    }
}
