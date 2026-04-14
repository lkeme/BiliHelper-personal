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

namespace Bhp\FilterWords;

use Bhp\Profile\ProfileContext;
use Bhp\Util\Resource\BaseResource;

class FilterWords extends BaseResource
{
    /**
     * 初始化 FilterWords
     * @param ProfileContext $profileContext
     * @param string $filename
     */
    public function __construct(
        private readonly ProfileContext $profileContext,
        string $filename = 'filter_library.json',
    )
    {
        $this->loadResource($filename, 'json');
    }

    /**
     * 重写真实路径
     * @param string $filename
     * @return string
     */
    protected function getFilePath(string $filename): string
    {
        return str_replace("\\", "/", $this->profileContext->resourcesPath() . $filename);
    }
}
