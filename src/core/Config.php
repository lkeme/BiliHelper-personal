<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Core;

use Jelix\IniFile\IniModifier;

class Config
{
    private static $app_config;
    private static $instance;

    /**
     * 不允许从外部调用以防止创建多个实例
     * 要使用单例，必须通过 Singleton::getInstance() 方法获取实例
     */
    private function __construct()
    {
    }

    /**
     * 防止实例被克隆（这会创建实例的副本）
     */
    private function __clone()
    {
    }

    /**
     * 防止反序列化（这将创建它的副本）
     */
    public function __wakeup()
    {

    }

    private static function getInstance(): Config
    {
        if (is_null(self::$instance)) {
            self::$instance = new static;
        }
        return self::$instance;
    }

    /**
     * @use 加载配置
     * @param string $load_file
     */
    public static function load(string $load_file)
    {
        $config_path = str_replace("\\", "/", APP_CONF_PATH . $load_file);
        if (!is_file($config_path)) {
            die("配置文件 {$load_file} 加载错误，请参照文档添加配置文件！");
        }
        // $config_path = dirname($config_path).DIRECTORY_SEPARATOR.$load_file;
        self::$app_config = new IniModifier($config_path);
    }


    public static function _set($name, $value, $section = 0, $key = null)
    {
        $_instance = self::getInstance();
        $_instance::$app_config->setValue($name, $value, $section, $key);
        $_instance::$app_config->save();
    }

    public static function _get($name, $section = 0, $key = null)
    {
        $_instance = self::getInstance();
        return $_instance::$app_config->getValue($name, $section, $key);
    }

    public static function _put()
    {
        $_instance = self::getInstance();

    }

    public static function _del()
    {
        $_instance = self::getInstance();

    }
}