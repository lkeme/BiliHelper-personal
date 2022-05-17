<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Core;

use BiliHelper\Util\Singleton;
use Jelix\IniFile\IniException;
use Jelix\IniFile\IniModifier;

class Config
{
    use Singleton;

    private string|array $load_file;
    private int|false $last_time;
    private IniModifier $app_config;
    private string $config_path;


    /**
     * @use 加载配置
     * @param string $load_file
     */
    public function load(string $load_file): void
    {
        $config_path = str_replace("\\", "/", APP_CONF_PATH . $load_file);
        if (!is_file($config_path)) {
            Env::failExit("配置文件 $load_file 加载错误，请参照文档添加配置文件！");
        }
        $this->load_file = $load_file;
        $this->config_path = $config_path;
        // $config_path = dirname($config_path).DIRECTORY_SEPARATOR.$load_file;
        $this->app_config = new IniModifier($this->config_path);
        $this->last_time = fileatime($this->config_path);
    }


    /**
     * @use 写入值
     * @param $name
     * @param $value
     * @param int|string $section
     * @param null $key
     * @throws IniException
     */
    public function _set($name, $value, int|string $section = 0, $key = null): void
    {
        $this->app_config->setValue($name, $value, $section, $key);
        $this->app_config->save();
        // 保存修改时间
        $this->last_time = fileatime($this->config_path);
    }

    /**
     * @use 获取值
     * @param $name
     * @param int|string $section
     * @param null $key
     * @return mixed
     */
    public function _get($name, int|string $section = 0, $key = null): mixed
    {
        // 判断是否被修改  重新加载文件
        // echo $this->last_time.PHP_EOL;
        // echo fileatime($this->config_path);
        if (fileatime($this->config_path) != $this->last_time) {
            $this->load($this->load_file);
        }
        return $this->app_config->getValue($name, $section, $key);
    }
}



