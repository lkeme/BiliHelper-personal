<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */


require 'vendor/autoload.php';


if ($argc >= 3) {
    if ($argv[2] == 'script') {
        define('__MODE__', 2);
    }
}
if (!defined('__MODE__')) {
    define('__MODE__', 1);
}


$app = new BiliHelper\Core\App(__DIR__);
$app->load($argv)
    ->inspect()
    ->start();
