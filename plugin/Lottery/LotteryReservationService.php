<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Lottery;

final class LotteryReservationService
{
    /**
     * @param array<string, mixed> $lottery
     * @return array{url: string, headers: array<string, string>, payload: array<string, int|string>}
     */
    public function buildReserveRequest(array $lottery, string $csrf, string $referer): array
    {
        return [
            'url' => 'https://api.vc.bilibili.com/dynamic_mix/v1/dynamic_mix/reserve_attach_card_button',
            'headers' => [
                'origin' => 'https://t.bilibili.com',
                'referer' => $referer,
            ],
            'payload' => [
                'cur_btn_status' => 1,
                'dynamic_id' => (string)$lottery['id_str'],
                'attach_card_type' => 'reserve',
                'reserve_id' => (int)$lottery['rid'],
                'reserve_total' => (int)$lottery['reserve_total'],
                'spmid' => '',
                'csrf' => $csrf,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $response
     * @param array<string, mixed> $lottery
     * @return array{success: bool, message: string}
     */
    public function evaluateReserveResponse(array $response, array $lottery): array
    {
        if (($response['code'] ?? -1) === 0) {
            return [
                'success' => true,
                'message' => "抽奖: 预约成功 ReserveId: {$lottery['rid']}  Toast: {$response['data']['toast']} 已有{$response['data']['desc_update']} ",
            ];
        }

        return [
            'success' => false,
            'message' => "抽奖: 预约失败 ReserveId: {$lottery['rid']}  Error: {$response['code']} -> {$response['message']}",
        ];
    }
}
