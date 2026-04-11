<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\DynamicLottery;

final class DynamicLotteryReservationService
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
                'message' => sprintf(
                    '动态抽奖: 参与成功 ReserveId: %s Toast: %s 已有%s',
                    (string)$lottery['rid'],
                    (string)($response['data']['toast'] ?? ''),
                    (string)($response['data']['desc_update'] ?? ''),
                ),
            ];
        }

        return [
            'success' => false,
            'message' => sprintf(
                '动态抽奖: 参与失败 ReserveId: %s Error: %s -> %s',
                (string)$lottery['rid'],
                (string)($response['code'] ?? ''),
                (string)($response['message'] ?? ''),
            ),
        ];
    }
}
