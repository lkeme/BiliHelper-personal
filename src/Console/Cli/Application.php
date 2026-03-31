<?php declare(strict_types=1);

namespace Bhp\Console\Cli;

final class Application
{
    /**
     * @var array<string, Command>
     */
    private array $commands = [];

    /**
     * @var array<string, string>
     */
    private array $aliases = [];

    private ?string $defaultCommand = null;
    private string $logo = '';

    public function __construct(
        private readonly string $name,
        private readonly string $version = '',
    ) {
    }

    public function add(Command $command, string $alias = '', bool $default = false): static
    {
        $this->commands[$command->name()] = $command;
        if ($alias !== '') {
            $command->addAlias($alias);
            $this->aliases[$alias] = $command->name();
        }

        if ($default) {
            $this->defaultCommand = $command->name();
        }

        return $this;
    }

    public function logo(string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function parse(array $argv): InputResult
    {
        $args = [];
        $count = count($argv);
        for ($index = 1; $index < $count; $index++) {
            $token = (string)$argv[$index];
            if (str_starts_with($token, '-')) {
                $next = $argv[$index + 1] ?? null;
                if (is_string($next) && $next !== '' && !str_starts_with($next, '-')) {
                    $args[] = $next;
                    $index++;
                }

                continue;
            }

            $args[] = $token;
        }

        return new InputResult($args);
    }

    public function handle(array $argv): void
    {
        [$command, $tokens] = $this->resolveCommand($argv);
        if ($command === null) {
            throw new RuntimeException('未找到可执行命令');
        }

        if ($this->shouldShowHelp($tokens)) {
            if ($this->shouldRenderOverview($tokens, $argv)) {
                $this->renderOverview();
                return;
            }

            $this->renderHelp($command);
            return;
        }

        $command->setValues($this->parseOptionValues($command, $tokens));
        $command->interact(new Interactor());
        $command->execute();
    }

    /**
     * @param array<int, mixed> $argv
     * @return array{0:?Command,1:string[]}
     */
    private function resolveCommand(array $argv): array
    {
        $tokens = array_values(array_map('strval', array_slice($argv, 1)));
        foreach ($tokens as $index => $token) {
            if ($token === '' || str_starts_with($token, '-')) {
                continue;
            }

            $resolved = $this->commands[$token] ?? $this->resolveAlias($token);
            if ($resolved === null) {
                continue;
            }

            unset($tokens[$index]);

            return [$resolved, array_values($tokens)];
        }

        return [$this->defaultCommand !== null ? ($this->commands[$this->defaultCommand] ?? null) : null, $tokens];
    }

    private function resolveAlias(string $alias): ?Command
    {
        $name = $this->aliases[$alias] ?? null;
        if ($name === null) {
            return null;
        }

        return $this->commands[$name] ?? null;
    }

    /**
     * @param string[] $tokens
     * @return array<string, mixed>
     */
    private function parseOptionValues(Command $command, array $tokens): array
    {
        $values = [];
        foreach ($command->options() as $option) {
            $values[$option['long']] = null;
        }

        $count = count($tokens);
        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if (!str_starts_with($token, '-')) {
                continue;
            }

            $option = $this->matchOption($command, $token);
            if ($option === null) {
                continue;
            }

            $next = $tokens[$index + 1] ?? null;
            if (is_string($next) && $next !== '' && !str_starts_with($next, '-')) {
                $values[$option['long']] = $next;
                $index++;
                continue;
            }

            $values[$option['long']] = true;
        }

        return $values;
    }

    /**
     * @return array{short:?string, long:string, description:string}|null
     */
    private function matchOption(Command $command, string $token): ?array
    {
        foreach ($command->options() as $option) {
            if ($token === '--' . $option['long']) {
                return $option;
            }

            if ($option['short'] !== null && $token === '-' . $option['short']) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @param string[] $tokens
     */
    private function shouldShowHelp(array $tokens): bool
    {
        return in_array('--help', $tokens, true) || in_array('-h', $tokens, true);
    }

    private function renderHelp(Command $command): void
    {
        if ($this->logo !== '') {
            fwrite(STDOUT, $this->logo . PHP_EOL);
        }

        fwrite(STDOUT, $this->name . ($this->version !== '' ? " {$this->version}" : '') . PHP_EOL);
        fwrite(STDOUT, $command->name() . ' ' . $command->description() . PHP_EOL);

        if ($command->usageText() !== '') {
            fwrite(STDOUT, $this->normalizeUsageText($command->usageText()) . PHP_EOL);
        }

        if ($command->options() === []) {
            return;
        }

        fwrite(STDOUT, 'Options:' . PHP_EOL);
        foreach ($command->options() as $option) {
            $signature = $option['short'] !== null
                ? sprintf('  -%s, --%s', $option['short'], $option['long'])
                : sprintf('      --%s', $option['long']);
            fwrite(STDOUT, $signature . ' ' . $option['description'] . PHP_EOL);
        }
    }

    /**
     * @param string[] $tokens
     * @param array<int, mixed> $argv
     */
    private function shouldRenderOverview(array $tokens, array $argv): bool
    {
        $nonOptionTokens = [];
        foreach (array_slice($argv, 1) as $token) {
            $value = (string)$token;
            if ($value === '' || str_starts_with($value, '-')) {
                continue;
            }

            $nonOptionTokens[] = $value;
        }

        return count($nonOptionTokens) === 0
            || (
                count($nonOptionTokens) === 1
                && !isset($this->commands[$nonOptionTokens[0]])
                && !isset($this->aliases[$nonOptionTokens[0]])
            );
    }

    private function renderOverview(): void
    {
        if ($this->logo !== '') {
            fwrite(STDOUT, $this->logo . PHP_EOL);
        }

        fwrite(STDOUT, $this->name . ($this->version !== '' ? " {$this->version}" : '') . PHP_EOL);
        fwrite(STDOUT, 'Commands:' . PHP_EOL);

        foreach ($this->commands as $command) {
            $aliases = $command->aliases();
            $aliasText = $aliases === [] ? '' : ' [' . implode(', ', $aliases) . ']';
            fwrite(STDOUT, sprintf('  %s%s %s', $command->name(), $aliasText, $command->description()) . PHP_EOL);
        }
    }

    private function normalizeUsageText(string $text): string
    {
        $normalized = str_replace('<eol/>', PHP_EOL, $text);

        return preg_replace('/<[^>]+>/', '', $normalized) ?? $normalized;
    }
}
