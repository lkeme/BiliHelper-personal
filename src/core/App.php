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
    }

    /**
     * @use 检查环境
     * @return $this
     */
    public function inspect(): App
    {
        (new Env())->inspect_configure()->inspect_extension();
        return $this;
    }

    /**
     * @use 加载配置
     * @param string $load_file
     * @return $this
     */
    public function load($load_file = 'user.ini'): App
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
            'CheckUpdate',
            'Login',
            'Schedule',
            'MainSite',
            'DailyBag',
            'ManGa',
            'ActivityLottery',
            'Competition',
            'DoubleHeart',
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
            'Forward',
            'CapsuleLottery',
            'PolishTheMedal',
            'VipPrivilege',
            'BpConsumption',
            // 'Silver', // Abandoned
            'Statistics',

        ];
        foreach ($plugins as $plugin) {
            $this->newTask($plugin);
        }
        Loop::run();
    }
}
