<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class LiveReservationLottery
{
    public function __construct(
        public readonly int $sid,
        public readonly string $name,
        public readonly int $vmid,
        public readonly int $reserveTotal,
        public readonly int $livePlanStartTime,
        public readonly string $jumpUrl,
        public readonly string $text,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromReservationEntry(array $data, int $referenceTime): ?self
    {
        if ((int)($data['etime'] ?? 0) <= $referenceTime) {
            return null;
        }

        if ((int)($data['is_follow'] ?? 0) !== 0) {
            return null;
        }

        if (!array_key_exists('lottery_type', $data) || !is_array($data['lottery_prize_info'] ?? null)) {
            return null;
        }

        $prizeInfo = $data['lottery_prize_info'];

        return new self(
            (int)($data['sid'] ?? 0),
            (string)($data['name'] ?? ''),
            (int)($data['up_mid'] ?? 0),
            (int)($data['total'] ?? 0),
            (int)($data['live_plan_start_time'] ?? 0),
            (string)($prizeInfo['jump_url'] ?? ''),
            (string)($prizeInfo['text'] ?? ''),
        );
    }

    /**
     * @return array{sid:int,name:string,vmid:int,reserve_total:int,live_plan_start_time:int,jump_url:string,text:string}
     */
    public function toArray(): array
    {
        return [
            'sid' => $this->sid,
            'name' => $this->name,
            'vmid' => $this->vmid,
            'reserve_total' => $this->reserveTotal,
            'live_plan_start_time' => $this->livePlanStartTime,
            'jump_url' => $this->jumpUrl,
            'text' => $this->text,
        ];
    }
}
