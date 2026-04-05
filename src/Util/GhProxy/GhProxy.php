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

namespace Bhp\Util\GhProxy;

use Bhp\Runtime\AppContext;

final class GhProxy
{
    public function __construct(
        private readonly AppContext $context,
    ) {
    }

    /**
     * @param string $url
     * @return string
     */
    public function mirror(string $url): string
    {
        if ($url === '') {
            return $url;
        }

        if (!$this->context->config('network_github.enable', false, 'bool')) {
            return $url;
        }

        $mirror = trim((string)$this->context->config('network_github.mirror', '', 'string'));
        if ($mirror !== '') {
            return $mirror . $url;
        }

        return $url;
    }
}
