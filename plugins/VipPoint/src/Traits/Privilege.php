<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;

trait Privilege
{
    use CommonTaskInfo;

    /**
     * 处理权益
     * @param array $data
     * @param string $name
     * @return bool
     */
    public function privilege(array $data, string $name): bool
    {
        $title = '体验任务';
        $code = 'privilege';
        if ($this->isComplete($data, $name, $title, $code)) {
            return true;
        }

        $this->worker($data, $name, $title, $code, 1);

        return false;
    }
}
