<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019 ~ 2020
 */


require 'vendor/autoload.php';


$filename = isset($argv[1]) ? $argv[1] : 'user.conf';

$app = new BiliHelper\Core\App();
$app->load(__DIR__, $filename);
$app->start();
