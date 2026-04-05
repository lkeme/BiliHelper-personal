<?php declare(strict_types=1);

namespace Bhp\Api\Credit;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiJury
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jury(): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/credit/v2/jury/jury', [], [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ], 'credit.jury.jury');
    }

    /**
     * @return array<string, mixed>
     */
    public function juryApply(): array
    {
        return $this->decodePost('pc', 'https://api.bilibili.com/x/credit/v2/jury/apply', [
            'csrf' => $this->request->csrfValue(),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ], 'credit.jury.apply');
    }

    /**
     * @return array<string, mixed>
     */
    public function caseNext(): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/credit/v2/jury/case/next', [], [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ], 'credit.jury.case_next');
    }

    /**
     * @return array<string, mixed>
     */
    public function caseInfo(string $caseId): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/credit/v2/jury/case/info', [
            'case_id' => $caseId,
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ], 'credit.jury.case_info');
    }

    /**
     * @return array<string, mixed>
     */
    public function caseOpinion(string $caseId, int $pn = 1, int $ps = 20): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/credit/v2/jury/case/opinion', [
            'case_id' => $caseId,
            'pn' => $pn,
            'ps' => $ps,
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ], 'credit.jury.case_opinion');
    }

    /**
     * @return array<string, mixed>
     */
    public function vote(string $caseId, int $vote, string $content = '', int $anonymous = 0, int $insiders = 0): array
    {
        return $this->decodePost('pc', 'https://api.bilibili.com/x/credit/v2/jury/vote', [
            'case_id' => $caseId,
            'vote' => $vote,
            'content' => $content,
            'anonymous' => $anonymous,
            'insiders' => $insiders,
            'csrf' => $this->request->csrfValue(),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ], 'credit.jury.vote');
    }

    /**
     * @return array<string, mixed>
     */
    public function caseList(int $pn = 1, int $ps = 25): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/credit/jury/caseList', [
            'pn' => $pn,
            'ps' => $ps,
            '_' => time(),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/judgement/index',
        ], 'credit.jury.case_list');
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
