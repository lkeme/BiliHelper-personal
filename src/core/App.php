<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Core;

use Amp\Loop;
use function Amp\asyncCall;
use BiliHelper\Plugin\Notice;


class App
{
    /**
     * App constructor.
     * @param string $app_path
     */
    public function __construct(string $app_path)
    {
        define('APP_CONF_PATH', $app_path . "/conf/");
        define('APP_DATA_PATH', $app_path . "/data/");
        define('APP_LOG_PATH', $app_path . "/log/");
        (new Env())->inspect_configure()->inspect_extension();
    }

    /**
     * @use 加载配置
     * @param string $load_file
     * @return $this
     */
    public function load($load_file = 'user.conf')
    {
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
                try {
                    call_user_func(array('BiliHelper\Plugin\\' . $taskName, 'run'), []);
                } catch (\Throwable  $e) {
                    $error_msg = "MSG: {$e->getMessage()} CODE: {$e->getCode()} FILE: {$e->getFile()} LINE: {$e->getLine()}";
                    Log::error($error_msg);
                    // Notice::push('error', $error_msg);
                }
                yield call_user_func(array('BiliHelper\Plugin\\' . $taskName, 'Delayed'), []);
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
            'Schedule',
            'MainSite',
            'Daily',
            'ManGa',
            'GameMatch',
            'ActivityLottery',
            'Competition',
            'Heart',
            'DailyTask',
            'Barrage',
            'Silver2Coin',
            'Judge',
            'GiftSend',
            'GroupSignIn',
            'GiftHeart',
            'SmallHeart',
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
            'Forward',
            'CapsuleLottery',
            // 'Silver',

        ];
        foreach ($plugins as $plugin) {
            $this->newTask($plugin);
        }
        Loop::run();
    }
}
