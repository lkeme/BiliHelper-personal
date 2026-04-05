<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;

trait ViewVipMall
{
    use CommonTaskInfo;

    public function viewVipMall(array $data, string $name): bool
    {
        $title = '日常任务';
        $code = 'vipmallview';
        if ($this->isComplete($data, $name, $title, $code)) {
            return true;
        }

        $this->worker($data, $name, $title, $code, 3);

        return false;
    }
}
