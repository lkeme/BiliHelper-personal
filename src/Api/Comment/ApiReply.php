<?php declare(strict_types=1);

namespace Bhp\Api\Comment;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

final class ApiReply
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function addVideoComment(string $aid, string $message): array
    {
        return $this->decodePost('pc', 'https://api.bilibili.com/x/v2/reply/add', [
            'type' => 1,
            'oid' => $aid,
            'message' => $message,
            'plat' => 1,
            'csrf' => $this->request->csrfValue(),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => "https://www.bilibili.com/video/av{$aid}",
        ], 'comment.reply.add');
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
