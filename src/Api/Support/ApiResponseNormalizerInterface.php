<?php declare(strict_types=1);

namespace Bhp\Api\Support;

interface ApiResponseNormalizerInterface
{
    /**
     * 处理标准化
     * @param mixed $payload
     * @param string $label
     * @return mixed
     */
    public function normalize(mixed $payload, string $label): mixed;
}
