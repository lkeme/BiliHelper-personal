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

namespace Bhp\Console;

use Ahc\Cli\Application;
use Bhp\Console\Command\AppCommand;
use Bhp\Console\Command\DebugCommand;
use Bhp\Console\Command\RestoreCommand;
use Bhp\Console\Command\ScriptCommand;
use Bhp\Util\DesignPattern\SingleTon;
use Exception;

class Console extends SingleTon
{
    /**
     * @var string
     */
    protected string $logo = <<<LOGO
 ________  ___  ___       ___  ___  ___  _______   ___       ________  _______   ________
|\   __  \|\  \|\  \     |\  \|\  \|\  \|\  ___ \ |\  \     |\   __  \|\  ___ \ |\   __  \
\ \  \|\ /\ \  \ \  \    \ \  \ \  \\\  \ \   __/|\ \  \    \ \  \|\  \ \   __/|\ \  \|\  \
 \ \   __  \ \  \ \  \    \ \  \ \   __  \ \  \_|/_\ \  \    \ \   ____\ \  \_|/_\ \   _  _\
  \ \  \|\  \ \  \ \  \____\ \  \ \  \ \  \ \  \_|\ \ \  \____\ \  \___|\ \  \_|\ \ \  \\  \|
   \ \_______\ \__\ \_______\ \__\ \__\ \__\ \_______\ \_______\ \__\    \ \_______\ \__\\ _\
    \|_______|\|__|\|_______|\|__|\|__|\|__|\|_______|\|_______|\|__|     \|_______|\|__|\|__|

LOGO;

    /**
     * @var array
     */
    protected array $argv;

    /**
     * @var Application
     */
    protected Application $app;


    /**
     * @return void
     */
    public function init(): void
    {

    }

    /**
     * 解析参数
     * @param array $argv
     * @param string $default
     * @return string
     */
    public static function parse(array $argv, string $default = 'user'): string
    {
        try {
            // backup
            // $argv = $argv ?? $_SERVER['argv'];
            // $filename = str_contains($argv[1] ?? '', ':') ? $default : $args[1] ?? $default;

            $argv = $argv ?? $_SERVER['argv'];
            self::getInstance()->argv = $argv;
            $app = new Application('parse');
            $args = $app->parse($argv);
            // 省略参数
            $filename = str_contains($args->args()[0] ?? '', ':') ? $default : $args->args()[0] ?? $default;
            unset($app);
        } catch (Exception $e) {
            failExit('解析命令行参数错误', ['msg' => $e->getMessage()]);
        }
        return $filename;
    }

    /**
     * @param array $argv
     * @return array
     */
    protected function transArgv(array $argv): array
    {
        if (!str_contains($argv[1], ':')) {
            unset($argv[1]);
        }
        return array_values($argv);
    }

    /**
     * @return void
     */
    public function register(): void
    {
        $this->app = new Application(getAppName(), getAppVersion());
        $this->app
            ->add(new AppCommand(), 'm:a', true) // 模式1
            ->add(new ScriptCommand(), 'm:s') // 模式2
            ->add(new RestoreCommand(), 'm:r')  // 模式3
            ->add(new DebugCommand(), 'm:d')  // 模式4
            ->logo($this->logo)
            ->handle($this->transArgv($this->argv));
    }

}
