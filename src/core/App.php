<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Core;

use Amp\Delayed;
use Amp\Loop;
use function Amp\asyncCall;

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
        if (PHP_SAPI != 'cli') {
            die("Please run this script from command line");
        }
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
     * @use 新任务
     * @param string $taskName
     */
    public function newTask(string $taskName)
    {
        asyncCall(function () use ($taskName) {
            while (true) {
                call_user_func(array('BiliHelper\Plugin\\' . $taskName, 'run'), []);
                yield new Delayed(1000);
            }
        });

    }

    /**
     * @use 核心运行
     */
    public function start()
    {
        $plugins = [
            'Login',
            'Sleep',
            'MasterSite',
            'Daily',
            'ManGa',
            'Heart',
            'Task',
            'Silver',
            'Barrage',
            'Silver2Coin',
            'GiftSend',
            'GroupSignIn',
            'GiftHeart',
            'MaterialObject',
            'AloneTcpClient',
            'ZoneTcpClient',
            'StormRaffle',
            'GiftRaffle',
            'PkRaffle',
            'GuardRaffle',
            'AnchorRaffle',
            'AwardRecord',
            'Statistics',
        ];
        foreach ($plugins as $plugin) {
            $this->newTask($plugin);
        }
        Loop::run();
    }
}