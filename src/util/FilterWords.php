<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Util;

use Sven\FileConfig\File;
use Sven\FileConfig\Store;
use Sven\FileConfig\Drivers\Json;

trait FilterWords
{
    protected static Store $store;
    protected static $store_status;
    protected static string $repository = APP_DATA_PATH . 'filter_library.json';

    /**
     * @use 加载配置信息
     * @return bool
     */
    protected static function loadJsonData(): bool
    {
        if (static::$store_status == date("Y/m/d")) {
            return false;
        }
        $file = new File(static::$repository);
        static::$store = new Store($file, new Json());

        static::$store_status = date("Y/m/d");
        return true;
    }

}