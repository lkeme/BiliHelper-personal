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

namespace Bhp\Api\WWW;

use Bhp\Request\Request;

class ApiMain
{
    /**
     * 初始化 ApiMain
     * @param Request $request
     */
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * 主站主页
     * @return array|mixed
     */
    public function home(): mixed
    {
        $url = 'https://www.bilibili.com/';
        return $this->request->fetchHeaders('pc', $url);
    }

    /**
     * video主页
     * @return array|mixed
     */
    public function video(string $bvid): mixed
    {
        $url = "https://www.bilibili.com/video/$bvid/";
        return $this->request->fetchHeaders('pc', $url);
    }


}
