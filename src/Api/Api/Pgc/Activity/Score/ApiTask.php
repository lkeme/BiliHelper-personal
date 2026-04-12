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
        'Referer' => 'https://big.bilibili.com/mobile/bigPoint/task',
    ];

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
        return $this->decodePost('app', 'https://api.bilibili.com/pgc/activity/score/task/sign', $this->request()->signCommonPayload([
            'disable_rcmd' => '0',
            'buvid' => $this->request()->buvidValue(),
            'csrf' => $this->request()->csrfValue(),
        ], true), self::HEADERS, 'pgc.score.sign');
    }

    /**
     * @return array<string, mixed>
     */
    public function receive(string $taskCode): array
    {
        return $this->decodePost('app', 'https://api.bilibili.com/pgc/activity/score/task/receive', $this->request()->signCommonPayload([
            'taskCode' => $taskCode,
            'csrf' => $this->request()->csrfValue(),
        ], true), self::HEADERS, 'pgc.score.receive');
    }

    /**
     * @return array<string, mixed>
     */
    public function complete(string $taskCode): array
    {
        return $this->decodePostJson('app', 'https://api.bilibili.com/pgc/activity/score/task/complete', $this->request()->signCommonPayload([
            'taskCode' => $taskCode,
            'csrf' => $this->request()->csrfValue(),
            'ts' => time(),
        ], true), array_merge([
            'Content-Type' => 'application/json',
        ], self::HEADERS), 'pgc.score.complete');
    }
}
