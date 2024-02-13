<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2024 ~ 2025
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;

class PluginTemplate extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'PluginTemplate', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '插件模板', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 9999, // 插件优先级
        'cycle' => '1(小时)', // 运行周期
        // 新增字段|时间段限制|默认全天运行
        // 'start' => '08:00:00', // 插件运行开始时间
        // 'end' => '23:00:00', // 插件运行结束时间
    ];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
//         // 时间锁
//        TimeLock::initTimeLock();
        // 缓存
//        Cache::initCache();
        // $this::class
//        $plugin->register($this, 'execute');
    }

    /**
     * 执行
     * @return void
     */
    public function execute(): void
    {
        // 时间锁限制
        // if (TimeLock::getTimes() > time()) return;

        // todo ...

        //TimeLock::setTimes(24 * 60 * 60);
    }


}
