<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;

trait ViewAnimate
{
    use CommonTaskInfo;

    public function viewAnimate(array $data, string $name): bool
    {
        $title = '日常任务';
        $code = 'animatetab';
        $channel = 'jp_channel';
        if ($this->isComplete($data, $name, $title, $code)) {
            return true;
        }

        $this->worker($data, $name, $title, $code, 2, $channel);

        return false;
    }
}
