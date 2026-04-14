<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class DynamicLotteryNotice
{
    /**
     * 初始化 DynamicLotteryNotice
     * @param int $businessId
     * @param int $lotteryId
     * @param int $status
     * @param bool $participated
     * @param bool $followed
     * @param bool $reposted
     */
    public function __construct(
        public readonly int $businessId,
        public readonly int $lotteryId,
        public readonly int $status,
        public readonly bool $participated,
        public readonly bool $followed,
        public readonly bool $reposted,
    ) {
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function fromResponse(array $response): ?self
    {
        if (($response['code'] ?? -1) !== 0) {
            return null;
        }

        $data = $response['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }

        return new self(
            (int)($data['business_id'] ?? 0),
            (int)($data['lottery_id'] ?? 0),
            (int)($data['status'] ?? -1),
            (bool)($data['participated'] ?? false),
            (bool)($data['followed'] ?? false),
            (bool)($data['reposted'] ?? false),
        );
    }
}
