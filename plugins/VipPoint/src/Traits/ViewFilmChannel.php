<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;

trait ViewFilmChannel
{
    use CommonTaskInfo;

    /**
     * 处理viewFilm渠道
     * @param array $data
     * @param string $name
     * @return bool
     */
    public function viewFilmChannel(array $data, string $name): bool
    {
        $title = '日常任务';
        $code = 'filmtab';
        $channel = 'tv_channel';
        if ($this->isComplete($data, $name, $title, $code)) {
            return true;
        }

        $this->worker($data, $name, $title, $code, 2, $channel);

        return false;
    }
}
