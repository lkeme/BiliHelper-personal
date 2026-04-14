<?php declare(strict_types=1);

namespace Bhp\Api\Support;

final class ApiResponseDecoder
{
    /**
     * 初始化 ApiResponseDecoder
     * @param ApiResponseNormalizerInterface $defaultJsonNormalizer
     * @param ApiResponseNormalizerInterface $defaultRawNormalizer
     */
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

    /**
     * 处理decodeRaw
     * @param string $raw
     * @param string $label
     * @param ApiResponseNormalizerInterface $normalizer
     * @return mixed
     */
    public function decodeRaw(
        string $raw,
        string $label,
        ?ApiResponseNormalizerInterface $normalizer = null,
    ): mixed {
        return $this->resolveRawNormalizer($normalizer)->normalize($raw, $label);
    }

    /**
     * 解析JSONNormalizer
     * @param ApiResponseNormalizerInterface $normalizer
     * @return ApiResponseNormalizerInterface
     */
    private function resolveJsonNormalizer(?ApiResponseNormalizerInterface $normalizer): ApiResponseNormalizerInterface
    {
        return $normalizer ?? $this->defaultJsonNormalizer ?? new StandardBilibiliNormalizer();
    }

    /**
     * 解析RawNormalizer
     * @param ApiResponseNormalizerInterface $normalizer
     * @return ApiResponseNormalizerInterface
     */
    private function resolveRawNormalizer(?ApiResponseNormalizerInterface $normalizer): ApiResponseNormalizerInterface
    {
        return $normalizer ?? $this->defaultRawNormalizer ?? new RawTextNormalizer();
    }
}
