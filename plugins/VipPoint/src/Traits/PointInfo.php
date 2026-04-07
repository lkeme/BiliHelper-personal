<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;


trait PointInfo
{
    public function pointInfo(array $data, string $name): bool
    {
        $point = $data['data']['point_info']['point'];
        $score_limit = $data['data']['task_info']['score_limit'];
        $score_month = $data['data']['task_info']['score_month'];
        $this->notice("大会员积分@{$name}: 当前拥有 {$point} 个积分，本月已获得 {$score_month}/{$score_limit} 大积分");

        return true;
    }
}
