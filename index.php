<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */


require 'vendor/autoload.php';


$filename = $argv[1] ?? 'user.ini';

$app = new BiliHelper\Core\App(__DIR__);
$app->load($filename)
    ->inspect()
    ->start();
