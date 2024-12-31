<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\Pay;

use Bhp\Request\Request;
use Bhp\Util\Common\Common;

class ApiWallet
{
    /**
     * 获取用户钱包
     * @return array
     */
    public static function getUserWallet(): array
    {
        $url = 'https://pay.bilibili.com/paywallet/wallet/getUserWallet';
        $ts = Common::getUnixTimestamp();
        $payload = [
            'panelType' => 3,
            'platformType' => 3,
            'timestamp' => $ts,
            'traceId' => $ts,
            'version' => '1.0',
        ];
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8',
            'origin' => 'https://pay.bilibili.com',
            'referer' => 'https://pay.bilibili.com/paywallet-fe/bb_balance.html'
        ];
        // {"errno":0,"msg":"SUCCESS","showMsg":"","errtag":0,"data":{"mid":1234,"totalBp":5.00,"defaultBp":0.00,"iosBp":0.00,"couponBalance":5.00,"availableBp":5.00,"unavailableBp":0.00,"unavailableReason":"苹果设备上充值的B币不能在其他平台的设备上进行使用","tip":null}}
        return Request::putJson(true, 'pc', $url, $payload, $headers);
    }

}
