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
    private $mode = 0;

    /**
     * App constructor.
     * @param string $app_path
     */
    public function __construct(string $app_path)
    {
        define('APP_CONF_PATH', $app_path . "/conf/");
        define('APP_DATA_PATH', $app_path . "/data/");
        define('APP_LOG_PATH', $app_path . "/log/");
        define('APP_TASK_PATH', $app_path . "/task/");
        define('APP_CACHE_PATH', $app_path . "/cache/");
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
     * @param $argv
     * @return $this
     */
    public function load($argv): App
    {
        $args = (new BCommand($argv))->run();
        $filename = $args->args()[0] ?? 'user.ini';
        $this->selectMode($args);
        Config::load($filename);
        return $this;
    }


    /**
     * @use 新任务
     * @param string $taskName
     * @param string $dir
     */
    private function newTask(string $taskName, string $dir)
    {
        asyncCall(function () use ($taskName, $dir) {
            while (true) {
                try {
                    call_user_func(array("BiliHelper\\$dir\\" . $taskName, 'run'), []);
                } catch (\Throwable  $e) {
                    // TODO 多次错误删除tasks_***.json文件
                    $error_msg = "MSG: {$e->getMessage()} CODE: {$e->getCode()} FILE: {$e->getFile()} LINE: {$e->getLine()}";
                    Log::error($error_msg);
                    // Notice::push('error', $error_msg);
                }
                if ($dir == 'Plugin')
                    yield call_user_func(array("BiliHelper\\$dir\\" . $taskName, 'Delayed'), []);
                else
                    break;
            }
        });
    }

    /**
     * @use Script模式
     */
    private function script_m()
    {
        $scripts = [
            'UnFollow' => '批量取消关注(暂测试)',
            'DelDynamic' => '批量清理动态(未完成)'
        ];

        $choice = \BiliHelper\Script\BaseTask::choice($scripts, 'UnFollow');
        $this->newTask($choice, 'Script');
    }

    /**
     * @use Loop模式
     */
    private function loop_m()
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
            $this->newTask($plugin, 'Plugin');
        }
        Loop::run();
    }

    /**
     * @use 选择模式
     * @param object $args
     */
    private function selectMode(object $args)
    {
        // 可能会有其他模式出现 暂定
        // 0 默认值 默认模式，1 脚本模式 ...
        if ($args->script) {
            $this->mode = 1;
        }
    }

    /**
     * @use 核心运行
     */
    public function start()
    {
        switch ($this->mode) {
            case 0:
                // 默认
                Log::info('执行Loop模式');
                $this->loop_m();
                break;
            case 1:
                // 脚本
                Log::info('执行Script模式');
                $this->script_m();
                break;
            default:
                Log::error("请检查，没有选定的执行模式");
                exit();
        }
    }
}
