<?php declare(strict_types=1);

namespace Bhp\Console\Cli;

abstract class Command extends Parser
{
    /**
     * @var array<string, array{short:?string, long:string, description:string}>
     */
    private array $options = [];

    /**
     * @var string[]
     */
    private array $aliases = [];

    private string $usageText = '';

    /**
     * 初始化 Command
     * @param string $name
     * @param string $description
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description = '',
    ) {
    }

    /**
     * 处理option
     * @param string $signature
     * @param string $description
     * @return static
     */
    public function option(string $signature, string $description = ''): static
    {
        $short = null;
        $long = null;

        foreach (preg_split('/\s+/', trim($signature)) ?: [] as $part) {
            if (str_starts_with($part, '--')) {
                $long = ltrim($part, '-');
                continue;
            }

            if (str_starts_with($part, '-')) {
                $short = ltrim($part, '-');
            }
        }

        if ($long === null || $long === '') {
            throw new RuntimeException("无效选项签名: {$signature}");
        }

        $this->options[$long] = [
            'short' => $short,
            'long' => $long,
            'description' => $description,
        ];

        return $this;
    }

    /**
     * 处理usage
     * @param string $text
     * @return static
     */
    public function usage(string $text): static
    {
        $this->usageText = $text;

        return $this;
    }

    /**
     * 处理名称
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * 处理description
     * @return string
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * 处理usageText
     * @return string
     */
    public function usageText(): string
    {
        return $this->usageText;
    }

    /**
     * @return array<string, array{short:?string, long:string, description:string}>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * @return string[]
     */
    public function aliases(): array
    {
        return $this->aliases;
    }

    /**
     * 处理addAlias
     * @param string $alias
     * @return void
     */
    public function addAlias(string $alias): void
    {
        if ($alias === '') {
            return;
        }

        $this->aliases[] = $alias;
    }

    /**
     * 处理interact
     * @param Interactor $io
     * @return void
     */
    public function interact(Interactor $io): void
    {
    }

    abstract public function execute(): void;
}
