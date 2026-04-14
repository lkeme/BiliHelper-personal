<?php declare(strict_types=1);

namespace Bhp\Api\Custom;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiCalcSign extends AbstractApiClient
{
    /**
     * 初始化 ApiCalcSign
     * @param Request $request
     */
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @param array<string, mixed> $t
     * @param array<int, int> $r
     * @return array<string, mixed>
     */
    public function heartBeat(string $url, array $t, array $r): array
    {
        return $this->decodePostJson('other', $url, [
            't' => $t,
            'r' => $r,
        ], [
            'Content-Type' => 'application/json',
        ], 'custom.calc_sign.heartBeat');
    }
}
