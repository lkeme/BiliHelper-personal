<?php declare(strict_types=1);

namespace Bhp\Api\XLive;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiXLiveSign extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function webGetSignInfo(): array
    {
        return $this->decodeGet('pc', 'https://api.live.bilibili.com/xlive/web-ucenter/v1/sign/WebGetSignInfo', [], [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index',
        ], 'xlive.sign.info');
    }

    /**
     * @return array<string, mixed>
     */
    public function doSign(): array
    {
        return $this->decodeGet('pc', 'https://api.live.bilibili.com/xlive/web-ucenter/v1/sign/DoSign', [], [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index',
        ], 'xlive.sign.do_sign');
    }
}
