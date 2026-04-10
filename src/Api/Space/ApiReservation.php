<?php declare(strict_types=1);

namespace Bhp\Api\Space;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiReservation extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function reservation(string $vmid): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/space/reservation', [
            'vmid' => $vmid,
        ], [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$vmid}/",
        ], 'space.reservation.list');
    }

    /**
     * @return array<string, mixed>
     */
    public function reserve(int $sid, int $vmid): array
    {
        return $this->decodePost('pc', 'https://api.bilibili.com/x/space/reserve', [
            'sid' => $sid,
            'jsonp' => 'jsonp',
            'csrf' => $this->request()->csrfValue(),
        ], [
            'content-type' => 'application/x-www-form-urlencoded',
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$vmid}/",
        ], 'space.reservation.reserve');
    }
}
