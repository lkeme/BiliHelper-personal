<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Plugin;

use Bhp\Log\Log;
use Bhp\Util\AsciiTable\AsciiTable;
use Bhp\Util\DesignPattern\SingleTon;

class Plugin extends SingleTon
{
    /**
     * @use 监听插件的启用/关闭|UUID下标
     * @access private
     * @var array
     */
    protected array $_staff = [];

    /**
     * @use 保存所有插件信息
     * @var array
     */
    protected array $_plugins = [];

    /**
     * @use 保存插件优先级信息
     * @var array
     */
    protected array $_priority = [];

    /**
     * @return void
     */
    public function init(): void
    {
        $this->detector();
    }

    /**
     * @return array
     */
    public static function getPlugins(): array
    {
        return self::getInstance()->_plugins;
    }

    /**
     * @return array
     */
    public static function getPluginsPriority(): array
    {
        return self::getInstance()->_priority;
    }

    /**
     * @return array
     */
    public static function getPluginsStaff(): array
    {
        return self::getInstance()->_staff;
    }

    /**
     * @use 这个是全局使用的触发钩子动作方法
     * @param string $hook
     * @param string $data
     * @return string
     */
    public function trigger(string $hook, mixed...$params): string
    {
        // 首先需要判断一下$hook 存不存在
        if (isset($this->_staff[$hook]) && is_array($this->_staff[$hook]) && count($this->_staff[$hook]) > 0) {
            $plugin_func_result = '';
            // 如果存在定义 $plugin_func_result
            foreach ($this->_staff[$hook] as $staff) {
                //  如果只是记录 请不要返回
                $plugin_func_result = '';
                $class = &$staff[0]; // 引用过来的类
                $method = $staff[1]; // 类下面的方法
                if (method_exists($class, $method)) {
                    $func_result = $class->$method(...$params);
                    if (is_numeric($func_result)) {
                        // 这里判断返回值是不是字符串,如果不是将不进行返回到页面上
                        $plugin_func_result .= $func_result;
                    }
                }
            }
        }
        return $plugin_func_result ?? '';
    }

    /**
     * @use 这里是在插件中使用的方法 用来注册插件
     * @param object $class_obj
     * @param string $method
     * @return void
     */
    public function register(object &$class_obj, string $method): void
    {
        $hook = get_class($class_obj);
        // 获取类名和方法名链接起来做下标
        $func_class = $hook . '->' . $method;
        // 将类和方法放入监听数组中 以$func_class做下标
        $this->_staff[$hook][$func_class] = array(&$class_obj, $method);
        // 每个插件必须实现的 getPluginInfo 获取插件信息
        $this->addPluginInfo($hook, $class_obj->getPluginInfo());
    }

    /**
     * @param string $hook
     * @param array $info
     * @return void
     */
    protected function addPluginInfo(string $hook, array $info): void
    {
        $info = $this->validatePlugins($hook, $info);
        //
        $this->_plugins[$hook] = $info;
        $this->_priority[] = $info['priority'];
    }

    /**
     * @param string $hook
     * @param array $info
     * @return array
     */
    protected function validatePlugins(string $hook, array $info): array
    {
        // 插件信息缺失
        $fillable = ['hook', 'name', 'version', 'desc', 'priority', 'cycle'];
        foreach ($fillable as $val) {
            if (!array_key_exists($val, $info)) {
                failExit("加载 $hook 插件错误，插件信息缺失，请检查修正.");
            }
        }
        // 插件名冲突
        if (array_key_exists($hook, $this->_plugins)) {
            failExit("加载 $hook 插件错误，插件名冲突，请检查修正.");
        }
        // 插件优先级冲突
        if (in_array($info['priority'], $this->_priority)) {
            failExit("加载 $hook 插件错误，插件优先级冲突，请检查修正.");
        }
        // 插件优先级定义
        if ($info['priority'] < 1000) {
            failExit("加载 $hook 插件错误，插件优先级定义错误，请检查修正.");
        }
        //
        $info['status'] = '√';
        //
        return $info;
    }

    /**
     * @use 初始化插件(all)
     * @return void
     */
    protected function detector(): void
    {
        // 主要功能为将插件需要执行功能放入  $_staff
        $plugins = $this->getActivePlugins();
        foreach ($plugins as $plugin) {
            // 这里将所有插件践行初始化
            // 路径请自己注意
            if (@file_exists($plugin['path'])) {
                include_once($plugin['path']);
                // 此时设定 文件夹名称 文件名称 类名 是统一的 如果想设定不统一请自己在get_active_plugins()内进行实现
                $class = $plugin['name'];
                if (class_exists($class)) {
                    // 初始化所有插件类
                    new $class($this);
                }
            }
        }
        $this->sortPlugins();
        $this->preloadPlugins();
    }

    /**
     * @use 获取插件信息(all)
     * @use 假定了插件在根目录的/plugin
     * @use 假定插件的入口和插件文件夹的名字是一样的
     * @use 注意:这个执行文件我放在了根目录 以下路径请根据实际情况获取
     * @return array
     */
    protected function getActivePlugins(): array
    {
        $plugins = [];
        $plugin_dir_name_arr = scandir(APP_PLUGIN_PATH);
        //
        foreach ($plugin_dir_name_arr as $_ => $v) {
            if ($v == "." || $v == "..") {
                continue;
            }
            // /plugin/Test/Test.php
            if (is_dir(APP_PLUGIN_PATH . $v)) {
                $path = APP_PLUGIN_PATH . $v . DIRECTORY_SEPARATOR . $v . '.php';
                $plugins[] = ['name' => $v, 'path' => $path];
            }
        }
        //
        return $plugins;
    }

    /**
     * @use 插件排序
     * @param string $column_key
     * @param int $sort_order
     * @return void
     */
    protected function sortPlugins(string $column_key = 'priority', int $sort_order = SORT_ASC): void
    {
        $arr = array_column($this->_plugins, $column_key);
        array_multisort($arr, $sort_order, $this->_plugins);
    }


    /**
     * @return void
     */
    protected function preloadPlugins(): void
    {
        $th_list = AsciiTable::array2table($this->_plugins, '预加载插件列表');
        foreach ($th_list as $item) {
            // Log::info($item);
            echo $item . PHP_EOL;
        }
    }

}