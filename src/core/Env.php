<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Core;

class Env
{
    private $app_name = 'BiliHelper Personal';
    private $app_version = '0.6.5.*';

    /**
     * Env constructor.
     */
    public function __construct()
    {
        set_time_limit(0);
        header("Content-Type:text/html; charset=utf-8");
        // ini_set('date.timezone', 'Asia/Shanghai');
        date_default_timezone_set('Asia/Shanghai');
        ini_set('display_errors', 'on');
        error_reporting(E_ALL);
    }

    /**
     * @use 检查扩展
     */
    public function inspect_extension()
    {
        $default_extensions = ['curl', 'openssl', 'sockets', 'json', 'zlib', 'mbstring'];
        foreach ($default_extensions as $extension) {
            if (!extension_loaded($extension)) {
                Log::error("检查到项目依赖 {$extension} 扩展未加载。");
                Log::error("请在 php.ini中启用 {$extension} 扩展后重试。");
                Log::error("程序常见问题请移步 https://github.com/lkeme/BiliHelper-personal 文档部分查看。");
                exit();
            }
        }
    }

    /**
     * @use 检查环境
     */
    public function inspect_configure()
    {
        Log::info("欢迎使用 {$this->app_name} 当前版本 {$this->app_version}");
        Log::info("使用说明请移步 https://github.com/lkeme/BiliHelper-personal 查看。");

        if (PHP_SAPI != 'cli') {
            die("Please run this script from command line .");
        }
        if (version_compare(PHP_VERSION, '7.0.0', '<')) {
            die("Please upgrade PHP version >= 7.0.0 .");
        }
        return $this;
    }
}
