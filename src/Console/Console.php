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
use Bhp\Console\Command\DebugCommand;
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
        'mode:script',
        'm:s',
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
        self::getInstance()->assertNoUnknownCommandToken($argv);

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
            ->add(new DebugCommand(), 'm:d')  // 模式2
            ->add(new ScriptCommand(), 'm:s') // 模式3
            ->logo($this->logo)
            ->handle($this->transArgv($this->argv));
    }

    /**
     * @return string[]
     */
    public function argv(): array
    {
        return array_values(array_map('strval', $this->argv));
    }

    public function mode(): string
    {
        foreach ($this->argv() as $token) {
            $token = trim((string)$token);
            if ($token === '' || str_starts_with($token, '-')) {
                continue;
            }

            if (!self::isCommandToken($token)) {
                continue;
            }

            return match ($token) {
                'mode:debug', 'm:d' => 'debug',
                'mode:script', 'm:s' => 'script',
                'mode:app', 'm:a' => 'app',
                default => 'app',
            };
        }

        return 'app';
    }

    private static function isCommandToken(string $token): bool
    {
        return in_array($token, self::COMMAND_TOKENS, true);
    }

    /**
     * @param string[] $argv
     */
    private function assertNoUnknownCommandToken(array $argv): void
    {
        foreach (array_slice($argv, 1) as $token) {
            $token = trim((string)$token);
            if (!self::looksLikeCommandToken($token)) {
                continue;
            }

            if (self::isCommandToken($token)) {
                return;
            }

            fwrite(STDERR, "未找到可执行命令: {$token}" . PHP_EOL);
            exit(1);
        }
    }

    private static function looksLikeCommandToken(string $token): bool
    {
        return preg_match('/^(?:m|mode):/i', $token) === 1;
    }

    private function shouldStripProfileToken(string $token): bool
    {
        return $token !== ''
            && !str_starts_with($token, '-')
            && !self::isCommandToken($token);
    }

}
