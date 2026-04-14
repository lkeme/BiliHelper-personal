<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Pool;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use InvalidArgumentException;

final class ActivityFlowPicker
{
    private int $cursor = 0;

    /**
     * 处理cursor
     * @return int
     */
    public function cursor(): int
    {
        return $this->cursor;
    }

    /**
     * 恢复Cursor
     * @param int $cursor
     * @return void
     */
    public function restoreCursor(int $cursor): void
    {
        if ($cursor < 0) {
            throw new InvalidArgumentException('cursor 必须大于等于 0');
        }

        $this->cursor = $cursor;
    }

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

