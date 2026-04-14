<?php declare(strict_types=1);

namespace Bhp\Api\Api\Pgc\Activity\Score;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiTask extends AbstractApiClient
{
    /**
     * @var array<string, string>
     */
    private const HEADERS = [
        'Referer' => 'https://big.bilibili.com/mobile/bigPoint?navhide=1&closable=1',
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
    public function sign(): array
    {
        return $this->decodePost('pc', 'https://api.bilibili.com/pgc/activity/score/task/sign2', [
            'csrf' => $this->request()->csrfValue(),
        ], self::HEADERS, 'pgc.score.sign');
    }

    /**
     * @return array<string, mixed>
     */
    public function receive(string $taskCode): array
    {
        return $this->decodePost('pc', 'https://api.bilibili.com/pgc/activity/score/task/receive/v2', [
            'taskCode' => $taskCode,
            'csrf' => $this->request()->csrfValue(),
        ], self::HEADERS, 'pgc.score.receive');
    }

    /**
     * @return array<string, mixed>
     */
    public function complete(string $taskCode): array
    {
        return $this->decodePost('pc', 'https://api.bilibili.com/pgc/activity/score/task/complete/v2', [
            'taskCode' => $taskCode,
            'csrf' => $this->request()->csrfValue(),
        ], self::HEADERS, 'pgc.score.complete');
    }
}
