<?php declare(strict_types=1);

namespace Bhp\Api\Api\Pgc\Activity\Score;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiTask extends AbstractApiClient
{
    /**
     * @var array<string, string>
     */
    private const PC_HEADERS = [
        'Referer' => 'https://big.bilibili.com/mobile/bigPoint?navhide=1&closable=1',
        'Content-Type' => 'application/json',
    ];

    /**
     * @var array<string, string>
     */
    private const APP_HEADERS = [
        'Referer' => 'https://big.bilibili.com/mobile/bigPoint/task',
        'navtive_api_from' => 'h5',
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
        return $this->decodePostJson('pc', 'https://api.bilibili.com/pgc/activity/score/task/sign2', [
            'csrf' => $this->request()->csrfValue(),
            't' => (int)round(microtime(true) * 1000),
        ], self::PC_HEADERS, 'pgc.score.sign');
    }

    /**
     * @return array<string, mixed>
     */
    public function receive(string $taskCode): array
    {
        return $this->decodePost('app', 'https://api.bilibili.com/pgc/activity/score/task/receive/v2', $this->request()->signCommonPayload([
            'taskCode' => $taskCode,
            'csrf' => $this->request()->csrfValue(),
            'ts' => time(),
        ], true), self::APP_HEADERS, 'pgc.score.receive');
    }

    /**
     * @return array<string, mixed>
     */
    public function complete(string $taskCode): array
    {
        return $this->decodePost('app', 'https://api.bilibili.com/pgc/activity/score/task/complete/v2', $this->request()->signCommonPayload([
            'taskCode' => $taskCode,
            'csrf' => $this->request()->csrfValue(),
        ], true), self::APP_HEADERS, 'pgc.score.complete');
    }
}
