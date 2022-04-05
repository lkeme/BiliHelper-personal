<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *  Source: https://learnku.com/articles/58105
 */

namespace BiliHelper\Plugins;

class Plugins
{
    /**
     * @use 监听插件的启用/关闭|UUID下标
     * @access private
     * @var array
     */
    private array $_staff = [];


    /**
     * @use 创建静态私有的变量保存该类对象
     * @var \BiliHelper\Plugins\Plugins
     */
    private static Plugins $instance;

    /**
     * @use 构造函数|防止使用new直接创建对象
     * @access public
     * @return void
     */
    private function __construct()
    {
        $this->detector();
    }

    /**
     * @use 防止使用clone克隆对象
     */
    private function __clone()
    {
    }

    /**
     * @use Singleton
     * @param mixed ...$args
     * @return \BiliHelper\Plugins\Plugins
     */
    public static function getInstance(...$args): Plugins
    {
        // 判断$instance是否是Singleton的对象，不是则创建
        if (!self::$instance instanceof self) {
            self::$instance = new self(...$args);
        }
        return self::$instance;
    }

    /**
     * @use 初始化所有插件类
     * @access public
     * @return void
     */
    public function detector()
    {
        //主要功能为将插件需要执行功能放入  $_staff
        $plugins = $this->get_active_plugins();

        if ($plugins) {
            foreach ($plugins as $plugin) {
                // 这里将所有插件践行初始化
                // 路径请自己注意
                if (@file_exists($plugin['path'])) {
                    include_once($plugin['path']);
                    // 此时设定 文件夹名称 文件名称 类名 是统一的 如果想设定不统一请自己在get_active_plugins()内进行实现
                    $class = $plugin['name'];
                    if (class_exists($class)) {
                        // 初始化所有插件类
                        new  $class($this);
                    }
                }
            }
        }
    }

    /**
     * 这里是在插件中使用的方法 用来注册插件
     *
     * @param string $hook
     * @param object $class_name
     * @param string $method
     */
    public function register(string $hook, object &$class_name, string $method)
    {
        // 获取类名和方法名链接起来做下标
        $func_class = get_class($class_name) . '->' . $method;
        // 将类和方法放入监听数组中 以$func_class做下标
        $this->_staff[$hook][$func_class] = array(&$class_name, $method);

    }

    /**
     * 这个是全局使用的触发钩子动作方法
     *
     * @param string $hook
     * @param string $data
     * @return string
     */
    public function trigger(string $hook, string $data = ''): string
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
                    $func_result = $class->$method($data);
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
     * 获取插件信息
     */
    public function get_active_plugins(): array
    {
        //  既假定了插件在根目录的/plugin
        //  我们再次假定插件的入口和插件文件夹的名字是一样的
        //  既假定了插件在根目录的/plugin
        //  注意:这个执行文件我放在了根目录 以下路径请根据实际情况获取

        $plugin_dir_path = '.' . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR;

        $plugin_dir_name_arr = scandir($plugin_dir_path);

        $plugins = array();
        foreach ($plugin_dir_name_arr as $k => $v) {
            if ($v == "." || $v == "..") {
                continue;
            }
            if (is_dir($plugin_dir_path . $v)) {
                $path = $plugin_dir_path . $v . DIRECTORY_SEPARATOR . $v . '.php';
                $plugins[] = ['name' => $v, 'path' => $path];
            }

        }
        return $plugins;
    }





}