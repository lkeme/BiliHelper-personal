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

use Bhp\Api\Support\ApiJson;

class ApiWallet
{
    /**
     * 获取用户钱包
     * @return array
     */
    public static function getUserWallet(): array
    {
        $url = 'https://pay.bilibili.com/payplatform/getUserWalletInfo';
        $headers = [
            'origin' => 'https://pay.bilibili.com',
            'referer' => 'https://pay.bilibili.com/paywallet-fe/bb_balance.html',
        ];

        return ApiJson::get('pc', $url, [], $headers, 'pay.wallet.get_user_wallet_info');
    }

}
