<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\BigPointExchange\Internal;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\pause;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

class BigPointPromptGateway
{
    public function __construct(
        ?bool $useRichPrompts = null,
    ) {
        $this->useRichPrompts = $useRichPrompts ?? (
            PHP_OS_FAMILY !== 'Windows'
            && function_exists('stream_isatty')
            && @stream_isatty(STDIN)
        );
    }

    private readonly bool $useRichPrompts;

    /**
     * @param array<int|string, string> $options
     */
    public function selectKey(
        string $label,
        array $options,
        int|string|null $default = null,
        string $hint = '',
    ): int|string|null {
        if ($options === []) {
            return null;
        }

        if ($this->useRichPrompts) {
            return select(
                label: $label,
                options: $options,
                default: $default,
                scroll: min(12, max(5, count($options))),
                hint: $hint,
            );
        }

        return $this->fallbackSelect($label, $options, $default);
    }

    /**
     * @param callable(string): array<int|string, string> $optionsResolver
     */
    public function searchKey(
        string $label,
        callable $optionsResolver,
        string $placeholder = '',
        string $hint = '',
    ): int|string|null {
        if ($this->useRichPrompts) {
            return search(
                label: $label,
                options: $optionsResolver(...),
                placeholder: $placeholder,
                scroll: 12,
                hint: $hint,
            );
        }

        return $this->fallbackSearch($label, $optionsResolver);
    }

    public function confirm(string $label, bool $default = false, string $hint = ''): bool
    {
        if ($this->useRichPrompts) {
            return confirm(label: $label, default: $default, hint: $hint);
        }

        $suffix = $default ? '[Y/n]' : '[y/N]';
        while (true) {
            $input = strtolower(trim($this->readLine("{$label} {$suffix}: ")));
            if ($input === '') {
                return $default;
            }

            if (in_array($input, ['y', 'yes'], true)) {
                return true;
            }

            if (in_array($input, ['n', 'no'], true)) {
                return false;
            }

            $this->writeLine('请输入 y 或 n。');
        }
    }

    public function pause(string $message = '按回车继续...'): void
    {
        if ($this->useRichPrompts) {
            pause($message);
            return;
        }

        $this->readLine($message);
    }

    /**
     * @param array<int|string, string> $options
     */
    private function fallbackSelect(string $label, array $options, int|string|null $default = null): int|string|null
    {
        $keys = array_keys($options);
        $values = array_values($options);

        $defaultIndex = null;
        if ($default !== null) {
            $matched = array_search($default, $keys, true);
            if ($matched !== false) {
                $defaultIndex = $matched + 1;
            }
        }

        while (true) {
            $this->writeLine('');
            $this->writeLine($label);
            foreach ($values as $index => $value) {
                $prefix = ($defaultIndex === ($index + 1)) ? '*' : ' ';
                $this->writeLine(sprintf('%s %d. %s', $prefix, $index + 1, $value));
            }

            $prompt = '请输入序号';
            if ($defaultIndex !== null) {
                $prompt .= " [默认 {$defaultIndex}]";
            }
            $prompt .= ': ';

            $input = trim($this->readLine($prompt));
            if ($input === '' && $defaultIndex !== null) {
                return $keys[$defaultIndex - 1];
            }

            if (ctype_digit($input)) {
                $selected = (int)$input;
                if ($selected >= 1 && $selected <= count($keys)) {
                    return $keys[$selected - 1];
                }
            }

            $this->writeLine('输入无效，请重新选择。');
        }
    }

    /**
     * @param callable(string): array<int|string, string> $optionsResolver
     */
    private function fallbackSearch(string $label, callable $optionsResolver): int|string|null
    {
        while (true) {
            $query = trim($this->readLine("{$label}（留空返回）: "));
            if ($query === '') {
                return null;
            }

            $options = $optionsResolver($query);
            if ($options === []) {
                $this->writeLine('没有匹配商品，请重试。');
                continue;
            }

            return $this->fallbackSelect('请选择商品', $options);
        }
    }

    private function readLine(string $prompt): string
    {
        fwrite(STDOUT, $prompt);
        $line = fgets(STDIN);

        return is_string($line) ? rtrim($line, "\r\n") : '';
    }

    private function writeLine(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}
