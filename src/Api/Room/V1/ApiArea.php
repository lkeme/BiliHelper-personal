<?php declare(strict_types=1);

namespace Bhp\Api\Room\V1;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiArea extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function getList(): array
    {
        return $this->decodeGet('other', 'https://api.live.bilibili.com/room/v1/Area/getList', [], [], 'room.area.get_list');
    }
}
