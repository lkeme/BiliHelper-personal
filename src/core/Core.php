<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Core;

use Bhp\Console\Console;
use Bhp\Util\DesignPattern\SingleTon;
use Bhp\Util\Os\Path;

class Core extends SingleTon
{
    /**
     * @var string
     */
    protected string $global_path;

    /**
     * @var string
     */
    protected string $profile_name;

    /**
     * @param string $global_path
     * @param string $profile_name
     * @return void
     */
    public function init(string $global_path, string $profile_name): void
    {
        $this->global_path = $global_path;
        $this->profile_name = $profile_name;
        // 定义全局常量
        $this->initSystemConstant();
        // 初始化全局文件夹
        $this->initSystemPath();
    }

    /**
     * 初始化全局常量
     * @return void
     */
    protected function initSystemConstant(): void
    {
        define('APP_MICROSECOND', 1000000);
        define('APP_RESOURCES_PATH', $this->global_path . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR);
        define('APP_PLUGIN_PATH', $this->global_path . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR);
        // Profile
        define('PROFILE_CONFIG_PATH', $this->fillPath('config'));
        define('PROFILE_DEVICE_PATH', $this->fillPath('device'));
        define('PROFILE_LOG_PATH', $this->fillPath('log'));
        define('PROFILE_TASK_PATH', $this->fillPath('task'));
        define('PROFILE_CACHE_PATH', $this->fillPath('cache'));

        // 判断profile/*是否存在存在
        if (!is_dir(PROFILE_CONFIG_PATH)) {
            die("加载 {$this->profile_name} 用户文档失败，请检查路径！");
        }
    }

    /**
     * 补充目录
     * @param string $path
     * @return string
     */
    protected function fillPath(string $path): string
    {
        // */profile/*/*
        return $this->global_path . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . $this->profile_name . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR;
    }

    /**
     * 初始化系统目录(创建、设置权限)
     * @return void
     */
    protected function initSystemPath(): void
    {
        $system_paths = [
            PROFILE_CONFIG_PATH,
            PROFILE_DEVICE_PATH,
            PROFILE_LOG_PATH,
            PROFILE_TASK_PATH,
            PROFILE_CACHE_PATH
        ];
        foreach ($system_paths as $path) {
            if (!file_exists($path)) {
                Path::CreateFolder($path);
                Path::SetFolderPermissions($path);
            }
        }
    }


}
