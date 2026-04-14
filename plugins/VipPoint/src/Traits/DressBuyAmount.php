<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;

trait DressBuyAmount
{
    use CommonTaskInfo;

    /**
     * 处理dressBuy数量
     * @param array $data
     * @param string $name
     * @return bool
     */
    public function dressBuyAmount(array $data, string $name): bool
    {
        $title = '日常任务';
        $code = 'dressbuyamount';
        if ($this->isComplete($data, $name, $title, $code)) {
            return true;
        }

        return $this->worker($data, $name, $title, $code, 4);
    }
}
