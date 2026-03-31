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

namespace Bhp\Console;

use Bhp\Console\Cli\Application;
use Bhp\Console\Command\AppCommand;
use Bhp\Console\Command\DoctorCommand;
use Bhp\Console\Command\DebugCommand;
use Bhp\Console\Command\ProfilesCommand;
use Bhp\Console\Command\RestoreCommand;
use Bhp\Console\Command\ScriptCommand;
use Bhp\Env\Env;
use Bhp\Util\DesignPattern\SingleTon;
use Bhp\Util\AppTerminator;

class Console extends SingleTon
{
    /**
     * @var string[]
     */
    private const COMMAND_TOKENS = [
        'mode:app',
        'm:a',
        'mode:debug',
        'm:d',
        'mode:restore',
        'm:r',
        'mode:script',
        'm:s',
        'mode:doctor',
        'm:o',
        'mode:profiles',
        'm:p',
    ];

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
     * @param array $reserved
     * @return string
     */
    public static function parse(array $argv, string $default = 'user', array $reserved = ['example']): string
    {
        $argv = $argv ?? $_SERVER['argv'];
        self::getInstance()->argv = $argv;

        $filename = $default;
        $first = (string)($argv[1] ?? '');
        if ($first !== '' && !str_starts_with($first, '-') && !self::isCommandToken($first)) {
            $filename = $first;
        }

        // 保留关键字
        if (in_array($filename, $reserved)) {
            AppTerminator::fail("不能使用程序保留关键字 {$filename}");
        }

        return $filename;
    }

    /**
     * @param array $argv
     * @return array
     */
    protected function transArgv(array $argv): array
    {
        if (isset($argv[1]) && $this->shouldStripProfileToken((string)$argv[1])) {
            unset($argv[1]);
        }

        return array_values($argv);
    }

    /**
     * @return void
     */
    public function register(): void
    {
        $env = Env::getInstance();
        $this->app = new Application($env->app_name, $env->app_version);
        $this->app
            ->add(new AppCommand(), 'm:a', true) // 模式1
            ->add(new ScriptCommand(), 'm:s') // 模式2
            ->add(new RestoreCommand(), 'm:r')  // 模式3
            ->add(new DebugCommand(), 'm:d')  // 模式4
            ->add(new DoctorCommand(), 'm:o')  // 模式5
            ->add(new ProfilesCommand(), 'm:p')  // 模式6
            ->logo($this->logo)
            ->handle($this->transArgv($this->argv));
    }

    private static function isCommandToken(string $token): bool
    {
        return in_array($token, self::COMMAND_TOKENS, true);
    }

    private function shouldStripProfileToken(string $token): bool
    {
        return $token !== ''
            && !str_starts_with($token, '-')
            && !self::isCommandToken($token);
    }

}
