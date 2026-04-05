<?php declare(strict_types=1);

namespace Bhp\Api\XLive\DataInterface\V1\HeartBeat;

use Bhp\Api\Custom\ApiCalcSign;
use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Bhp\Util\Common\Common;
use Throwable;

class ApiHeartBeat
{
    private readonly ApiCalcSign $calcSignApi;

    public function __construct(
        private readonly Request $request,
        ?ApiCalcSign $calcSignApi = null,
    ) {
        $this->calcSignApi = $calcSignApi ?? new ApiCalcSign($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function mobileHeartBeat(int $roomId, int $upId, int $parentId, int $areaId, string $heartbeatUrl): array
    {
        $payload = [
            'platform' => 'android',
            'uuid' => Common::customCreateUUID($this->request->uidValue()),
            'buvid' => $this->request->buvidValue(),
            'seq_id' => '1',
            'room_id' => $roomId,
            'parent_id' => $parentId,
            'area_id' => $areaId,
            'timestamp' => time() - 60,
            'secret_key' => 'axoaadsffcazxksectbbb',
            'watch_time' => '60',
            'up_id' => $upId,
            'up_level' => '40',
            'jump_from' => '30000',
            'gu_id' => strtoupper(Common::randString(43)),
            'play_type' => '0',
            'play_url' => '',
            's_time' => '0',
            'data_behavior_id' => '',
            'data_source_id' => '',
            'up_session' => "l:one:live:record:{$roomId}:" . time() - 88888,
            'visit_id' => strtoupper(Common::randString(32)),
            'watch_status' => '%7B%22pk_id%22%3A0%2C%22screen_status%22%3A1%7D',
            'click_id' => Common::customCreateUUID(strrev($this->request->uidValue())),
            'session_id' => '',
            'player_type' => '0',
            'client_ts' => time(),
        ];
        $payload['client_sign'] = $this->calcClientSign($heartbeatUrl, $payload);

        try {
            $raw = $this->request->postText('app', 'https://live-trace.bilibili.com/xlive/data-interface/v1/heartbeat/mobileHeartBeat', $this->request->signCommonPayload($payload), [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'xlive.heartbeat.mobile 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'xlive.heartbeat.mobile');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function calcClientSign(string $heartbeatUrl, array $payload): string
    {
        $response = $this->calcSignApi->heartBeat($heartbeatUrl, $payload, [3, 7, 2, 6, 8]);
        return (string)($response['s'] ?? '');
    }
}
