<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;

trait DressView
{
    use CommonTaskInfo;

    public function dressView(array $data, string $name): bool
    {
        $title = '日常任务';
        $code = 'dress-view';
        if ($this->isComplete($data, $name, $title, $code)) {
            return true;
        }

        $this->worker($data, $name, $title, $code, 1);

        return false;
    }
}
