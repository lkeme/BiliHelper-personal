<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;

trait ViewVideo
{
    use CommonTaskInfo;

    /**
     * @var array<array{season_id: string, ep_id: string, name: string}>
     */
    private const OGV_DRAMA_ENTRIES = [
        ['season_id' => '33622', 'ep_id' => '327107', 'name' => '西游记·猴王初问世'],
        ['season_id' => '33623', 'ep_id' => '327339', 'name' => '西游记续集·险渡通天河'],
        ['season_id' => '33624', 'ep_id' => '327843', 'name' => '红楼梦·林黛玉别父进京都'],
        ['season_id' => '33625', 'ep_id' => '327285', 'name' => '水浒传·高俅发迹'],
        ['season_id' => '33626', 'ep_id' => '327584', 'name' => '三国演义·桃园三结义'],
    ];

    /**
     * @return array{season_id: string, ep_id: string, name: string}
     */
    protected function getRandomDrama(): array
    {
        return self::OGV_DRAMA_ENTRIES[array_rand(self::OGV_DRAMA_ENTRIES)];
    }

    /**
     * 处理观看正片 (ogvwatchnew)
     * @param array $data
     * @param string $name
     * @return bool
     */
    public function viewVideo(array $data, string $name): bool
    {
        $title = '日常任务';
        $code = 'ogvwatchnew';
        if ($this->isComplete($data, $name, $title, $code)) {
            return true;
        }

        return $this->completeOgvWatch($data, $name, $title, $code);
    }
}
