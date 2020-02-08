<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Core;

use Dotenv\Dotenv;


class Config
{
    private static $app_config;
    private static $config_path;
    private static $instance;

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static;
        }
        return self::$instance;
    }


    public static function load($load_file)
    {
        return self::getInstance()::_load($load_file);
    }

    public static function put($key, $val = null)
    {
        return self::getInstance()::_put($key, $val);
    }

    public static function get($key = null)
    {
        return self::getInstance()::_get($key);
    }

    /**
     * @use 加载配置文件
     * @param string $load_file
     */
    private static function _load($load_file)
    {
        $config_path = str_replace("\\", "/", APP_PATH . "/conf/{$load_file}");
        if (!is_file($config_path)) {
            die("配置文件 {$load_file} 加载错误，请参照文档添加配置文件！");
        }
        $app_config = Dotenv::createImmutable(dirname($config_path), $load_file);
        $app_config->load();
        self::$app_config = $app_config;
        self::$config_path = $config_path;
    }


    /**
     * @use 写入配置
     * @param $key
     * @param $val
     * @return bool
     */
    private static function _put($key, $val)
    {
        if (!is_null($val)) {
            if (!empty(self::$config_path)) {
                file_put_contents(self::$config_path, preg_replace(
                    '/^' . $key . '=\S*/m',
                    $key . '=' . $val,
                    file_get_contents(self::$config_path)
                ));
            }
        }
        putenv($key . '=' . $val);
        // self::$app_config->load();
        return true;
    }

    /**
     * @use 读出配置
     * @param string|null $key
     * @return mixed|null
     */
    private static function _get($key)
    {
        if (self::$app_config->required($key)) {
            return getenv($key);
        }
        return null;
    }

}