<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Bootstrap;

use Bhp\Cache\Cache;
use Bhp\Console\Console;
use Bhp\Core\Core;
use Bhp\Device\Device;
use Bhp\FilterWords\FilterWords;
use Bhp\Log\Log;
use Bhp\Notice\Notice;
use Bhp\Plugin\Plugin;
use Bhp\Request\Request;
use Bhp\Runtime\Runtime;
use Bhp\Sign\Sign;
use Bhp\User\User;
use Bhp\Util\DesignPattern\SingleTon;
use Bhp\Env\Env;
use Bhp\Config\Config;

class Bootstrap extends SingleTon
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
     * @var array<int, string>
     */
    protected array $argv = [];

    /**
     * @param string $global_path
     * @param array $argv
     * @return void
     */
    public function init(string $global_path, array $argv): void
    {
        $this->global_path = $global_path;
        $this->argv = array_values(array_map('strval', $argv));
        $this->profile_name = Console::parse($argv);
        if ($this->isHelpRequest($this->argv)) {
            $this->registerForHelp();
            return;
        }

        $this->superRegister();
    }

    public function superRegister(): void
    {
        // 核心
        Core::getInstance($this->global_path, $this->profile_name);
        // 环境
        Env::getInstance();
        // 插件中心
        Plugin::getInstance();
        // 配置
        Config::getInstance();
        // 缓存中心
        Cache::getInstance();
        // 日志
        Log::getInstance();
        // 设备/取前缀
        Device::getInstance();
        // 运行时上下文
        Runtime::getInstance();
        // 当前 profile 启动自检
        (new StartupSelfCheck())->run();
        // 请求中心
        Request::getInstance();
        // 过滤词
        FilterWords::getInstance();
        // 签名
        Sign::getInstance();
        // 用户
        User::getInstance();
        // 通知中心
        Notice::getInstance();
        // 控制台
        Console::getInstance();
    }

    public function registerForHelp(): void
    {
        Core::getInstance($this->global_path, $this->profile_name);
        Config::getInstance();
        Log::getInstance();
        Env::getInstance();
        Runtime::getInstance();
        Console::getInstance();
    }

    /**
     * @return void
     */
    public function run(): void
    {
        Console::getInstance()->register();
    }

    /**
     * @param array<int, string> $argv
     */
    protected function isHelpRequest(array $argv): bool
    {
        return in_array('--help', $argv, true) || in_array('-h', $argv, true);
    }
}
