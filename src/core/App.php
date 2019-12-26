<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019 ~ 2020
 */

namespace BiliHelper\Core;

use BiliHelper\Plugin;

class App
{
    /**
     * App constructor.
     */
    public function __construct()
    {
        set_time_limit(0);
        header("Content-Type:text/html; charset=utf-8");
        date_default_timezone_set('Asia/Shanghai');
    }

    /**
     * @use 加载配置
     * @param $app_path
     * @param string $load_file
     * @return $this
     */
    public function load($app_path, $load_file = 'user.conf')
    {
        // define('APP_PATH', dirname(__DIR__));
        define('APP_PATH', $app_path);
        Config::load($load_file);
        return $this;
    }

    /**
     * @use 核心运行
     */
    public function start()
    {
        while (true) {
            Plugin\Login::run();
            Plugin\Sleep::run();
            Plugin\MasterSite::run();
            Plugin\Daily::run();
            Plugin\Heart::run();
            Plugin\Task::run();
            Plugin\Silver::run();
            Plugin\Barrage::run();
            Plugin\Silver2Coin::run();
            Plugin\GiftSend::run();
            Plugin\GroupSignIn::run();
            Plugin\GiftHeart::run();
            Plugin\MaterialObject::run();
            Plugin\AloneTcpClient::run();
            Plugin\ZoneTcpClient::run();
            Plugin\StormRaffle::run();
            Plugin\GiftRaffle::run();
            Plugin\PkRaffle::run();
            Plugin\GuardRaffle::run();
            Plugin\AnchorRaffle::run();
            Plugin\AwardRecord::run();
            Plugin\Statistics::run();
            usleep(0.1 * 1000000);
        }
    }
}