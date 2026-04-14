<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\Video;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiShare extends AbstractApiClient
{
    /**
     * 初始化 ApiShare
     * @param Request $request
     */
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * 分享视频
     * @param string $aid
     * @return array
     */
    public function share(string $aid): array
    {
        $url = 'https://api.bilibili.com/x/web-interface/share/add';
        $payload = [
            'aid' => $aid,
            'csrf' => $this->request()->csrfValue(),
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'Referer' => "https://www.bilibili.com/video/av$aid",
        ];
        // {"code":0,"message":"0","ttl":1}
        return $this->decodePost('pc', $url, $payload, $headers, 'video.share');
    }
}
