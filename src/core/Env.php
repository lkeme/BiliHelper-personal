<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Core;

use JetBrains\PhpStorm\NoReturn;
use function JBZoo\Data\json;

class Env
{
    private string $app_name;
    private string $app_version;
    private string $app_branch;
    private string $app_source;

    private string $repository = APP_DATA_PATH . 'latest_version.json';


    /**
     * Env constructor.
     */
    public function __construct()
    {
        set_time_limit(0);
        // header("Content-Type:text/html; charset=utf-8");
        // ini_set('date.timezone', 'Asia/Shanghai');
        date_default_timezone_set('Asia/Shanghai');
        ini_set('display_errors', 'on');
        error_reporting(E_ALL);

        $this->loadJsonData();
    }

    /**
     * @use 检查扩展
     */
    public function inspect_extension(): void
    {
        $default_extensions = ['curl', 'openssl', 'sockets', 'json', 'zlib', 'mbstring'];
        foreach ($default_extensions as $extension) {
            if (!extension_loaded($extension)) {
                Log::error("检查到项目依赖 $extension 扩展未加载。");
                Log::error("请在 php.ini中启用 $extension 扩展后重试。");
                Log::error("程序常见问题请移步 $this->app_source 文档部分查看。");
                exit();
            }
        }
    }

    /**
     * @use 检查环境
     */
    public function inspect_configure(): Env
    {
        Log::info("欢迎使用 项目: $this->app_name@$this->app_branch 版本: $this->app_version");
        Log::info("使用说明请移步 $this->app_source 查看");

        if (PHP_SAPI != 'cli') {
            Env::failExit('Please run this script from command line .');
        }
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            Env::failExit('Please upgrade PHP version < 8.0.0 .');
        }
        return $this;
    }

    /**
     * @use 加载本地JSON DATA
     */
    private function loadJsonData(): void
    {
        $conf = json($this->repository);
        $this->app_name = $conf->get('project', 'BiliHelper-personal');
        $this->app_version = $conf->get('version', '0.0.0.000000');
        $this->app_branch = $conf->get('branch', 'master');
        $this->app_source = $conf->get('source', 'https://github.com/lkeme/BiliHelper-personal');
    }

    /**
     * @use Check: running in docker?
     * @return bool
     */
    public static function isDocker(): bool
    {
        // Win直接跳出
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return false;
        }
        if (!file_exists('/.dockerenv')) {
            return false;
        }
        // 检查/proc/1/cgroup内是否包含"docker"等字符串；
        if (!is_file('/proc/self/cgroup') || !preg_match('%^\d+:\w+:/(docker|actions_job)/' . preg_quote(gethostname(), '%') . '\w+%sm', file_get_contents('/proc/self/cgroup'))) {
            return false;
        }
//        $processStack = explode(PHP_EOL, shell_exec('cat /proc/self/cgroup | grep docker'));
//        $processStack = array_filter($processStack);
//        return count($processStack) > 0;
        return true;
    }

    /**
     * @use 错误退出
     * @param $message
     * @param array $context
     * @param int $delay
     * @return void
     */
    #[NoReturn]
    public static function failExit($message, array $context = [],int $delay = 60 * 2): void
    {
        Log::error($message, $context);
        // 如果在docker环境中，延迟退出，方便查看错误
        if (self::isDocker()) {
            // 暂停两分钟后自动退出
            sleep($delay);
        }
        die();
    }
}
