<?php declare(strict_types=1);

namespace Bhp\Api\Comment;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Bhp\User\User;

final class ApiReply
{
    /**
     * @return array<string, mixed>
     */
    public static function addVideoComment(string $aid, string $message): array
    {
        $user = User::parseCookie();
        $url = 'https://api.bilibili.com/x/v2/reply/add';
        $payload = [
            'type' => 1,
            'oid' => $aid,
            'message' => $message,
            'plat' => 1,
            'csrf' => $user['csrf'],
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => "https://www.bilibili.com/video/av{$aid}",
        ];

        return ApiJson::post('pc', $url, $payload, $headers, 'comment.reply.add');
    }
}
