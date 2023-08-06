<?php declare(strict_types=1);

use Bhp\Log\Log;

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */
trait PointInfo
{

    /**
     * @param array $data
     * @param string $name
     * @return bool
     */
    public function pointInfo(array $data, string $name): bool
    {
        $ts = date('Y-m-d H:i:s', $data['data']['current_ts']);
        $point = $data['data']['point_info']['point'];
        Log::notice("大会员积分@$name: 截至 $ts 您当前拥有 $point 个积分");
        //
        $score_limit = $data['data']['task_info']['score_limit'];
        $score_month = $data['data']['task_info']['score_month'];
        Log::notice("大会员积分@$name: 本月已经获得 $score_month/$score_limit 大积分");
        //
        $base_score = $data['data']['task_info']['sing_task_item']['base_score'];
        $sign_count = $data['data']['task_info']['sing_task_item']['count'];
        Log::notice("大会员积分@$name: 已连续签到 $sign_count 天 获取到 $base_score 个大积分");
        return true;
    }

}
