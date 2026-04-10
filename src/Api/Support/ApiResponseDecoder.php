<?php declare(strict_types=1);

namespace Bhp\Api\Support;

final class ApiResponseDecoder
{
    public function __construct(
        private readonly ?ApiResponseNormalizerInterface $defaultJsonNormalizer = null,
        private readonly ?ApiResponseNormalizerInterface $defaultRawNormalizer = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeJson(
        string $raw,
        string $label,
        ?ApiResponseNormalizerInterface $normalizer = null,
    ): array {
        $decoded = ApiJson::decode($raw, $label);
        $normalized = $this->resolveJsonNormalizer($normalizer)->normalize($decoded, $label);

        return is_array($normalized) ? $normalized : [
            'code' => -500,
            'message' => "{$label} 响应格式异常",
            'data' => [],
        ];
    }

    public function decodeRaw(
        string $raw,
        string $label,
        ?ApiResponseNormalizerInterface $normalizer = null,
    ): mixed {
        return $this->resolveRawNormalizer($normalizer)->normalize($raw, $label);
    }

    private function resolveJsonNormalizer(?ApiResponseNormalizerInterface $normalizer): ApiResponseNormalizerInterface
    {
        return $normalizer ?? $this->defaultJsonNormalizer ?? new StandardBilibiliNormalizer();
    }

    private function resolveRawNormalizer(?ApiResponseNormalizerInterface $normalizer): ApiResponseNormalizerInterface
    {
        return $normalizer ?? $this->defaultRawNormalizer ?? new RawTextNormalizer();
    }
}
