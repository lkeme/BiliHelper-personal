<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityInfoUpdate\Internal;

final class EraActivityFollowTarget
{
    public function __construct(
        public readonly string $uid,
        public readonly string $uname,
        public readonly bool $addLotteryTimes = false,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['uid'] ?? ''),
            (string)($data['uname'] ?? ''),
            (bool)($data['add_lottery_times'] ?? $data['addLotteryTimes'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uid' => $this->uid,
            'uname' => $this->uname,
            'add_lottery_times' => $this->addLotteryTimes,
        ];
    }
}
