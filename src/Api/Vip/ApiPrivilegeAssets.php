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

class ApiPrivilegeAssets extends AbstractApiClient
{
    /**
     * @var array<string, string>
     */
    protected array $headers = [
        'Referer' => 'https://big.bilibili.com/mobile/cardBag?closable=1&navhide=1&tab=all',
    ];

    /**
     * 初始化 ApiPrivilegeAssets
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
    public function list(): array
    {
        $url = 'https://api.bilibili.com/x/vip/privilege_assets/list';
        $payload = [
            'csrf' => $this->request()->csrfValue(),
        ];

        return $this->decodeGet('app', $url, $this->request()->signCommonPayload($payload), $this->headers, 'vip.privilege_assets.list');
    }

    /**
     * @return array<string, mixed>
     */
    public function exchange(string $token): array
    {
        $url = 'https://api.bilibili.com/x/vip/privilege_assets/exchange';
        $payload = [
            'token' => $token,
            'csrf' => $this->request()->csrfValue(),
        ];

        return $this->decodePost('app', $url, $this->request()->signCommonPayload($payload), $this->headers, 'vip.privilege_assets.exchange');
    }
}
