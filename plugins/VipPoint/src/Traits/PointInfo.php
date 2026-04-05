<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;


trait PointInfo
{
    public function pointInfo(array $data, string $name): bool
    {
        $ts = date('Y-m-d H:i:s', $data['data']['current_ts']);
        $point = $data['data']['point_info']['point'];
        $this->notice("大会员积分@{$name}: 截至 {$ts} 您当前拥有 {$point} 个积分");

        $score_limit = $data['data']['task_info']['score_limit'];
        $score_month = $data['data']['task_info']['score_month'];
        $this->notice("大会员积分@{$name}: 本月已经获得 {$score_month}/{$score_limit} 大积分");

        return true;
    }
}
