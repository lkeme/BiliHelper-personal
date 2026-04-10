<?php declare(strict_types=1);

namespace Bhp\Api\DynamicSvr;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiDynamicSvr extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function followUpDynamic(): array
    {
        return $this->decodeGet('pc', 'https://api.vc.bilibili.com/dynamic_svr/v1/dynamic_svr/dynamic_new', [
            'uid' => $this->request()->uidValue(),
            'type_list' => '8,512,4097,4098,4099,4100,4101',
        ], [
            'origin' => 'https://t.bilibili.com',
            'referer' => 'https://t.bilibili.com/pages/nav/index_new',
        ], 'dynamic_svr.follow_up_dynamic');
    }
}
