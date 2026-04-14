<?php declare(strict_types=1);

namespace Bhp\Api\Comment;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

final class ApiReply extends AbstractApiClient
{
    /**
     * 初始化 ApiReply
     * @param Request $request
     */
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
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
            'csrf' => $this->request()->csrfValue(),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => "https://www.bilibili.com/video/av{$aid}",
        ], 'comment.reply.add');
    }
}
