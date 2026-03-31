<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class DynamicReserveLottery
{
    public function __construct(
        public readonly int $reserveTotal,
        public readonly int $rid,
        public readonly string $title,
        public readonly int $upMid,
        public readonly string $prize,
        public readonly string $idStr,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromDetailData(array $data): ?self
    {
        $reserve = $data['item']['modules']['module_dynamic']['additional']['reserve'] ?? null;
        if (!is_array($reserve)) {
            return null;
        }

        if (!(bool)($data['item']['visible'] ?? false)) {
            return null;
        }

        $button = $reserve['button'] ?? null;
        if (!is_array($button)) {
            return null;
        }

        if (($button['uncheck']['text'] ?? null) !== '预约'
            || ($button['status'] ?? null) != 1
            || ($button['type'] ?? null) != 2) {
            return null;
        }

        if (($reserve['state'] ?? null) != 0 || ($reserve['stype'] ?? null) != 2) {
            return null;
        }

        return new self(
            (int)$reserve['reserve_total'],
            (int)$reserve['rid'],
            (string)$reserve['title'],
            (int)$reserve['up_mid'],
            (string)$reserve['desc3']['text'],
            (string)$data['item']['id_str'],
        );
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'reserve_total' => $this->reserveTotal,
            'rid' => $this->rid,
            'title' => $this->title,
            'up_mid' => $this->upMid,
            'prize' => $this->prize,
            'id_str' => $this->idStr,
        ];
    }
}
