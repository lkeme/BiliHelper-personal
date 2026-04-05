<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;

trait BuyVipProduct
{
    use CommonTaskInfo;

    public function buyVipProduct(array $data, string $name): bool
    {
        $title = '日常任务';
        $code = 'subscribe';
        if ($this->isComplete($data, $name, $title, $code)) {
            return true;
        }

        return $this->worker($data, $name, $title, $code, 4);
    }
}
