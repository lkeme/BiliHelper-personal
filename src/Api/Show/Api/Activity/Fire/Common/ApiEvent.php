<?php declare(strict_types=1);

namespace Bhp\Api\Show\Api\Activity\Fire\Common;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiEvent extends AbstractApiClient
{
    /**
     * @var array<string, string>
     */
    private const HEADERS = [
        'Referer' => 'https://big.bilibili.com/mobile/bigPoint/task',
    ];

    /**
     * 初始化 ApiEvent
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
    public function dispatch(): array
    {
        $url = 'https://show.bilibili.com/api/activity/fire/common/event/dispatch?' . http_build_query(
            $this->request()->signCommonPayload([
                'csrf' => $this->request()->csrfValue(),
            ], true)
        );

        return $this->decodePostJson('app', $url, [
            'eventId' => 'hevent_oy4b7h3epeb',
        ], array_merge([
            'content-type' => 'application/json; charset=utf-8',
        ], self::HEADERS), 'show.activity.fire.dispatch');
    }
}
