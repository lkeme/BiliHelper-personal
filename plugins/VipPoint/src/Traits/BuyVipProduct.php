<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;

trait BuyVipProduct
{
    use CommonTaskInfo;

    /**
     * 处理buy大会员Product
     * @param array $data
     * @param string $name
     * @return bool
     */
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
