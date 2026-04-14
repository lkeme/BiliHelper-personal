<?php declare(strict_types=1);

namespace Bhp\Api\Api\Pgc\Activity\Deliver;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiTask extends AbstractApiClient
{
    /**
     * @var array<string, string>
     */
    private const HEADERS = [
        'Referer' => 'https://big.bilibili.com/mobile/bigPoint/task',
    ];

    /**
     * 初始化 ApiTask
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
    public function complete(string $position): array
    {
        return $this->decodePost('app', 'https://api.bilibili.com/pgc/activity/deliver/task/complete', $this->request()->signCommonPayload([
            'disable_rcmd' => '0',
            'position' => $position,
            'csrf' => $this->request()->csrfValue(),
        ], true), self::HEADERS, 'pgc.deliver.complete');
    }
}
