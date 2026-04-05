<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;

trait Bonus
{
    use CommonTaskInfo;

    public function bonus(array $data, string $name): bool
    {
        $title = '福利任务';
        $code = 'bonus';
        if ($this->isComplete($data, $name, $title, $code)) {
            return true;
        }

        $this->worker($data, $name, $title, $code, 1);

        return false;
    }
}
