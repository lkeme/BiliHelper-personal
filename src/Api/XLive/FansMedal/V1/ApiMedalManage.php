<?php declare(strict_types=1);

namespace Bhp\Api\XLive\FansMedal\V1;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

final class ApiMedalManage
{
    private const PANEL_URL = 'https://api.live.bilibili.com/xlive/app-ucenter/v1/fansMedal/panel';
    private const DELETE_URL = 'https://api.live.bilibili.com/fans_medal/v5/live_fans_medal/deleteMedals';
    private const LIST_PAGE_SIZE_MAX = 50;

    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array{
     *     code: int,
     *     message: string,
     *     items: array<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     page_size: int,
     *     has_more: bool,
     *     next_page: int
     * }
     */
    public function listPage(int $page, int $pageSize = self::LIST_PAGE_SIZE_MAX): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(self::LIST_PAGE_SIZE_MAX, $pageSize));

        try {
            $raw = $this->request->getText('pc', self::PANEL_URL, [
                'page' => $page,
                'page_size' => $pageSize,
            ], [
                'origin' => 'https://live.bilibili.com',
                'referer' => 'https://live.bilibili.com/',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'fans_medal.manage.list 请求失败: ' . $throwable->getMessage(),
                'items' => [],
                'total' => 0,
                'page' => $page,
                'page_size' => $pageSize,
                'has_more' => false,
                'next_page' => $page,
            ];
        }

        $decoded = ApiJson::decode($raw, 'fans_medal.manage.list');
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $pageInfo = is_array($data['page_info'] ?? null) ? $data['page_info'] : [];

        return [
            'code' => (int)($decoded['code'] ?? -1),
            'message' => trim((string)($decoded['message'] ?? $decoded['msg'] ?? '')),
            'items' => $this->normalizePanelItems($data),
            'total' => max(0, (int)($data['total_number'] ?? 0)),
            'page' => max(1, (int)($pageInfo['current_page'] ?? $page)),
            'page_size' => max(1, (int)($pageInfo['number'] ?? $pageSize)),
            'has_more' => (bool)($pageInfo['has_more'] ?? false),
            'next_page' => max($page, (int)($pageInfo['next_page'] ?? $page)),
        ];
    }

    /**
     * @param int[] $medalIds
     * @return array<string, mixed>
     */
    public function deleteMedals(array $medalIds): array
    {
        $medalIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): int => (int)$value,
            $medalIds,
        ), static fn (int $value): bool => $value > 0)));

        if ($medalIds === []) {
            return [
                'code' => -400,
                'message' => 'fans_medal.manage.delete 请求失败: medalIds 不能为空',
                'data' => [],
            ];
        }

        try {
            $raw = $this->request->postText('pc', self::DELETE_URL, [
                'medalIds' => count($medalIds) === 1 ? (string)$medalIds[0] : implode(',', $medalIds),
                'csrf' => $this->request->csrfValue(),
                'csrf_token' => $this->request->csrfValue(),
            ], [
                'origin' => 'https://live.bilibili.com',
                'referer' => 'https://live.bilibili.com/p/html/live-app-fansmedal-manange/index.html',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'fans_medal.manage.delete 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'fans_medal.manage.delete');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function normalizePanelItems(array $data): array
    {
        $items = [];
        foreach (['list', 'special_list'] as $bucket) {
            $rows = $data[$bucket] ?? [];
            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $normalized = $this->normalizePanelItem($row);
                if ($normalized !== null) {
                    $items[] = $normalized;
                }
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function normalizePanelItem(array $row): ?array
    {
        $medal = is_array($row['medal'] ?? null) ? $row['medal'] : [];
        $roomInfo = is_array($row['room_info'] ?? null) ? $row['room_info'] : [];
        $anchorInfo = is_array($row['anchor_info'] ?? null) ? $row['anchor_info'] : [];

        $medalId = (int)($medal['medal_id'] ?? 0);
        $targetId = (int)($medal['target_id'] ?? 0);
        if ($medalId <= 0 || $targetId <= 0) {
            return null;
        }

        return [
            'medal_id' => $medalId,
            'target_id' => $targetId,
            'level' => (int)($medal['level'] ?? 0),
            'medal_name' => trim((string)($medal['medal_name'] ?? '')),
            'anchor_name' => trim((string)($anchorInfo['nick_name'] ?? $medal['target_name'] ?? '')),
            'room_id' => (int)($roomInfo['room_id'] ?? 0),
            'living_status' => (int)($roomInfo['living_status'] ?? 0),
            'is_lighted' => (int)($medal['is_lighted'] ?? 0),
            'can_delete' => (bool)($row['can_delete'] ?? true),
            'raw' => $row,
        ];
    }
}
