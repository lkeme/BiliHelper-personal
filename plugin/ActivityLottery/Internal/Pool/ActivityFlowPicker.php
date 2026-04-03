<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Pool;

use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;

final class ActivityFlowPicker
{
    private int $cursor = 0;

    /**
     * @param ActivityFlow[] $flows
     * @return ActivityFlow[]
     */
    public function pick(array $flows, int $limit): array
    {
        if ($flows === [] || $limit <= 0) {
            return [];
        }

        $count = count($flows);
        $size = min($limit, $count);
        $start = $this->cursor % $count;

        $picked = [];
        for ($offset = 0; $offset < $size; $offset++) {
            $index = ($start + $offset) % $count;
            $picked[] = $flows[$index];
        }

        $this->cursor = ($start + $size) % $count;

        return $picked;
    }
}

