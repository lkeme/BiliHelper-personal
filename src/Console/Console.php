<?php declare(strict_types=1);

namespace Bhp\Console;

use Bhp\Console\Cli\Application;
use Bhp\Console\Cli\RuntimeException as CliRuntimeException;
use Bhp\Console\Command\AppCommand;
use Bhp\Console\Command\DebugCommand;
use Bhp\Console\Command\ScriptCommand;
use Bhp\Env\Env;
use LogicException;

class Console
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
     * @var string[]
     */
    protected array $argv;

    protected Application $app;

    /**
     * @param string[] $argv
     */
    public function __construct(
        array $argv = [],
        private readonly ?Env $env = null,
        private readonly ?AppCommand $appCommand = null,
        private readonly ?DebugCommand $debugCommand = null,
        private readonly ?ScriptCommand $scriptCommand = null,
    ) {
        $this->argv = array_values(array_map('strval', $argv ?: ($_SERVER['argv'] ?? [])));
        self::assertNoUnknownCommandToken($this->argv);
    }

    /**
     * @param string[] $argv
     * @param string[] $reserved
     */
    public static function parse(array $argv, string $default = 'user', array $reserved = ['example']): string
    {
        self::assertNoUnknownCommandToken($argv);

        $filename = trim($default);
        $first = (string)($argv[1] ?? '');
        if ($first !== '' && !str_starts_with($first, '-') && !self::isCommandToken($first)) {
            $filename = trim($first);
        }

        if ($filename === '') {
            throw new CliRuntimeException('profile 名称不能为空');
        }

        if (!self::isSafeProfileName($filename)) {
            throw new CliRuntimeException("profile 名称非法: {$filename}");
        }

        if (in_array($filename, $reserved, true)) {
            throw new CliRuntimeException("不能使用程序保留关键字 {$filename}");
        }

        return $filename;
    }

    /**
     * @param string[] $argv
     */
    public static function isHelpRequest(array $argv): bool
    {
        return in_array('--help', $argv, true) || in_array('-h', $argv, true);
    }

    /**
     * @param string[] $argv
     */
    public static function isScriptListRequest(array $argv): bool
    {
        return self::resolveMode($argv) === 'script'
            && (in_array('--list', $argv, true) || in_array('-l', $argv, true));
    }

    /**
     * @param string[] $argv
     */
    public static function isReadOnlyRequest(array $argv): bool
    {
        return self::isHelpRequest($argv) || self::isScriptListRequest($argv);
    }

    /**
     * @param string[] $argv
     * @return string[]
     */
    protected function transArgv(array $argv): array
    {
        if (isset($argv[1]) && $this->shouldStripProfileToken((string)$argv[1])) {
            unset($argv[1]);
        }

        return array_values($argv);
    }

    /**
     * 处理注册
     * @return void
     */
    public function register(): void
    {
        $env = $this->env;
        if (!$env instanceof Env) {
            throw new LogicException('Console environment dependency is not configured.');
        }
        if (!$this->appCommand instanceof AppCommand) {
            throw new LogicException('Console app command dependency is not configured.');
        }
        if (!$this->debugCommand instanceof DebugCommand) {
            throw new LogicException('Console debug command dependency is not configured.');
        }
        if (!$this->scriptCommand instanceof ScriptCommand) {
            throw new LogicException('Console script command dependency is not configured.');
        }

        $this->app = new Application($env->app_name, $env->app_version);
        $this->app
            ->add($this->appCommand, 'm:a', true)
            ->add($this->debugCommand, 'm:d')
            ->add($this->scriptCommand, 'm:s')
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

    /**
     * 处理模式
     * @return string
     */
    public function mode(): string
    {
        return self::resolveMode($this->argv());
    }

    /**
     * @param string[] $argv
     */
    public static function resolveMode(array $argv): string
    {
        foreach ($argv as $token) {
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

    /**
     * 判断命令令牌是否满足条件
     * @param string $token
     * @return bool
     */
    private static function isCommandToken(string $token): bool
    {
        return in_array($token, self::COMMAND_TOKENS, true);
    }

    /**
     * @param string[] $argv
     */
    private static function assertNoUnknownCommandToken(array $argv): void
    {
        foreach (array_slice($argv, 1) as $token) {
            $token = trim((string)$token);
            if (!self::looksLikeCommandToken($token)) {
                continue;
            }

            if (self::isCommandToken($token)) {
                continue;
            }

            throw new CliRuntimeException("未找到可执行命令: {$token}");
        }
    }

    /**
     * 处理looksLike命令令牌
     * @param string $token
     * @return bool
     */
    private static function looksLikeCommandToken(string $token): bool
    {
        return preg_match('/^(?:m|mode):/i', $token) === 1;
    }

    /**
     * 判断Strip画像令牌是否满足条件
     * @param string $token
     * @return bool
     */
    private function shouldStripProfileToken(string $token): bool
    {
        return $token !== ''
            && !str_starts_with($token, '-')
            && !self::isCommandToken($token);
    }

    /**
     * 判断Safe画像名称是否满足条件
     * @param string $name
     * @return bool
     */
    private static function isSafeProfileName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9_-]{0,63})$/', $name) === 1;
    }
}
