<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;

trait BuyVipVideo
{
    use CommonTaskInfo;

    /**
     * 处理buy大会员视频
     * @param array $data
     * @param string $name
     * @return bool
     */
    public function buyVipVideo(array $data, string $name): bool
    {
        $title = '日常任务';
        $code = 'tvodbuy';
        if ($this->isComplete($data, $name, $title, $code)) {
            return true;
        }

        return $this->worker($data, $name, $title, $code, 4);
    }
}
