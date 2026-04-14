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

namespace Bhp\Api\Vip;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;
use Bhp\WbiSign\WbiSign;

class ApiUser extends AbstractApiClient
{
    /**
     * 初始化 ApiUser
     * @param Request $request
     */
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function userVipInfo(): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/vip/web/user/info', [], [
            'origin' => 'https://account.bilibili.com',
            'referer' => 'https://account.bilibili.com/account/home',
        ], 'vip.user.info');
    }

    /**
     * @return array<string, mixed>
     */
    public function userNavInfo(): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/web-interface/nav', [], [
            'origin' => 'https://space.bilibili.com',
            'referer' => 'https://space.bilibili.com/' . $this->request()->uidValue(),
        ], 'vip.user.nav');
    }

    /**
     * @return array<string, mixed>
     */
    public function userSpaceInfo(int $uid = 0): array
    {
        $uid = $uid !== 0 ? $uid : (int)$this->request()->uidValue();

        return $this->decodeGet('pc', 'https://api.bilibili.com/x/space/wbi/acc/info', WbiSign::encryption([
            'mid' => $uid,
            'platform' => 'web',
        ]), [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ], 'vip.user.space');
    }
}
