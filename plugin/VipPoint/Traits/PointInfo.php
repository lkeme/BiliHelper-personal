<?php declare(strict_types=1);

use Bhp\Log\Log;

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
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
        $now = date('Y-m-d H:i:s', $data['data']['current_ts']);
        $point = $data['data']['point_info']['point'];

        Log::notice("大会员积分@{$name}: 截至 {$now} 您当前拥有 {$point} 个积分");
        return true;
    }

}
