<?php declare(strict_types=1);

namespace Bhp\Api\Esports;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiGuess
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function collectionQuestion(int $pn, int $ps = 50): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/esports/guess/collection/question', [
            'pn' => $pn,
            'ps' => $ps,
            'stime' => date('Y-m-d H:i:s', strtotime(date('Y-m-d', time()))),
            'etime' => date('Y-m-d H:i:s', strtotime(date('Y-m-d', time())) + 86400 - 1),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/v/game/match/competition',
        ], 'esports.guess.collection_question');
    }

    /**
     * @return array<string, mixed>
     */
    public function guessAdd(int $oid, int $mainId, int $detailId, int $count): array
    {
        return $this->decodePost('pc', 'https://api.bilibili.com/x/esports/guess/add', [
            'oid' => $oid,
            'main_id' => $mainId,
            'detail_id' => $detailId,
            'count' => $count,
            'is_fav' => 0,
            'csrf' => $this->request->csrfValue(),
            'csrf_token' => $this->request->csrfValue(),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/v/game/match/competition',
        ], 'esports.guess.add');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function decodeGet(string $os, string $url, array $payload, array $headers, string $label): array
    {
        try {
            $raw = $this->request->getText($os, $url, $payload, $headers);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => "{$label} 请求失败: {$throwable->getMessage()}",
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, $label);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function decodePost(string $os, string $url, array $payload, array $headers, string $label): array
    {
        try {
            $raw = $this->request->postText($os, $url, $payload, $headers);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => "{$label} 请求失败: {$throwable->getMessage()}",
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, $label);
    }
}
