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

namespace Bhp\Util\AsciiTable;

use Closure;

class AsciiTable
{
    const AlignLeft = STR_PAD_RIGHT;
    const AlignCenter = STR_PAD_BOTH;
    const AlignRight = STR_PAD_LEFT;

    /**
     * @var array
     */
    protected array $data;

    /**
     * @var array
     */
    protected array $keys;

    /**
     * @var array
     */
    protected array $widths;

    /**
     * @var string
     */
    protected string $indentation;

    /**
     * @var bool
     */
    protected bool $displayHeader = true;

    /**
     * @var int
     */
    protected int $keysAlignment;

    /**
     * @var int
     */
    protected int $valuesAlignment;

    /**
     * @var mixed
     */
    protected mixed $formatter;

    /**
     * @var string|null
     */
    protected ?string $title;

    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setData($data)
            ->setIndentation('')
            ->setKeysAlignment(self::AlignCenter)
            ->setValuesAlignment(self::AlignLeft)
            ->setTitle(null)
            ->setFormatter(null);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getTable();
    }

    /**
     * @param array|null $data
     * @return string
     */
    public function getTable(?array $data = null): string
    {
        if (!is_null($data))
            $this->setData($data);

        $data = $this->prepare();
        $this->expandWidthsForTitle();

        $i = $this->indentation;

        if ($this->keys === []) {
            return $this->renderTitleOnlyTable($i);
        }

        $table = $i . $this->line('┌', '─', '┬', '┐') . PHP_EOL;

        if ($this->title !== null && $this->title !== '') {
            $table .= $i . $this->fullWidthRow($this->title, self::AlignCenter) . PHP_EOL;
            if ($this->displayHeader || $data !== []) {
                $table .= $i . $this->line('├', '─', '┬', '┤') . PHP_EOL;
            }
        }

        if ($this->displayHeader) {
            //绘制table header
            $headerRows = array_combine($this->keys, $this->keys);
            $table .= $i . $this->row($headerRows, $this->keysAlignment) . PHP_EOL;
            $table .= $i . $this->line('├', '─', '┼', '┤') . PHP_EOL;
        }

        foreach ($data as $row) {
            $table .= $i . $this->row($row, $this->valuesAlignment) . PHP_EOL;
        }
        $table .= $i . $this->line('└', '─', '┴', '┘') . PHP_EOL;

        return $table;
    }

    /**
     * @param string|null $title
     * @return $this
     */
    public function setTitle(?string $title): static
    {
        $title = trim((string)$title);
        $this->title = $title !== '' ? $title : null;

        return $this;
    }

    /**
     * @param string $indentation
     * @return $this
     */
    public function setIndentation(string $indentation): static
    {
        $this->indentation = $indentation;
        return $this;
    }

    /**
     * @param bool $displayHeader
     * @return $this
     */
    public function isDisplayHeader(bool $displayHeader): static
    {
        $this->displayHeader = $displayHeader;
        return $this;
    }

    /**
     * @param int $keysAlignment
     * @return $this
     */
    public function setKeysAlignment(int $keysAlignment): static
    {
        $this->keysAlignment = $keysAlignment;
        return $this;
    }

    /**
     * @param int $valuesAlignment
     * @return $this
     */
    public function setValuesAlignment(int $valuesAlignment): static
    {
        $this->valuesAlignment = $valuesAlignment;
        return $this;
    }

    /**
     * @param Closure|null $formatter
     * @return $this
     */
    public function setFormatter(?Closure $formatter): static
    {
        $this->formatter = $formatter;
        return $this;
    }

    /**
     * @param string|null $left
     * @param string $horizontal
     * @param string $link
     * @param string|null $right
     * @return string
     */
    protected function line(?string $left, string $horizontal, string $link, ?string $right): string
    {
        $left = $left ?? '';
        $right = $right ?? '';
        $line = $left;
        foreach ($this->keys as $key) {
            $line .= str_repeat($horizontal, $this->widths[$key] + 2) . $link;
        }

        if (mb_strlen($line) > mb_strlen($left)) {
            $line = mb_substr($line, 0, -mb_strlen($horizontal));
        }
        return $line . $right;
    }

    /**
     * @param array $row
     * @param int $alignment
     * @return string
     */
    protected function row(array $row, int $alignment): string
    {
        $line = '│';
        foreach ($this->keys as $key) {
            $value = (string)($row[$key] ?? '');
            $line .= ' ' . static::mbStrPad($value, $this->widths[$key], ' ', $alignment) . ' ' . '│';
        }
        if (empty($row)) {
            $line .= '│';
        }
        return $line;
    }

    /**
     * @param int $alignment
     * @return string
     */
    protected function fullWidthRow(string $value, int $alignment): string
    {
        $innerWidth = max(2, $this->innerWidth());

        return '│ ' . $this->mbStrPad($value, $innerWidth - 2, ' ', $alignment) . ' │';
    }

    /**
     * @return array
     */
    protected function prepare(): array
    {
        $this->keys = [];
        $this->widths = [];
        $data = $this->data;

        //合并全部数组的key
        foreach ($data as $row) {
            $this->keys = array_merge($this->keys, array_keys($row));
        }
        $this->keys = array_unique($this->keys);

        //补充缺陷数组
        foreach ($data as $index => $row) {
            foreach ($this->keys as $key) {
                if (!array_key_exists($key, $row)) {
                    $data[$index][$key] = null;
                }
            }
        }

        //执行formatter
        if ($this->formatter instanceof Closure) {
            foreach ($data as &$row) {
                array_walk($row, $this->formatter);
            }
            unset($row);
        }

        foreach ($this->keys as $key) {
            $this->setWidth($key, $key);
        }
        foreach ($data as $row) {
            foreach ($row as $columnKey => $columnValue) {
                $this->setWidth($columnKey, $columnValue);
            }
        }
        return $data;
    }

    /**
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    protected function setWidth(mixed $key, mixed $value): void
    {
        if (!isset($this->widths[$key])) {
            $this->widths[$key] = 0;
        }
        // Deprecated: strlen(): Passing null to parameter #1 ($string) of type string is deprecated
        // Deprecated: mb_strlen(): Passing null to parameter #1 ($string) of type string is deprecated
        $value = (string)($value ?: '');
        $width = (strlen($value) + mb_strlen($value, 'UTF8')) / 2;
        if ($width > $this->widths[$key]) {
            $this->widths[$key] = $width;
        }
    }

    /**
     * @param string $data
     * @return bool|int|null
     */
    protected static function countCJK(string $data): bool|int|null
    {
        return preg_match_all('/[\p{Han}\p{Katakana}\p{Hiragana}\p{Hangul}]/u', $data);
    }

    /**
     * @param string $input
     * @param int $pad_length
     * @param string $pad_string
     * @param int $pad_type
     * @param string|null $encoding
     * @return string
     */
    protected function mbStrPad(string $input, int $pad_length, string $pad_string = ' ', int $pad_type = STR_PAD_RIGHT, ?string $encoding = null): string
    {
        // $encoding = $encoding === null ? mb_internal_encoding() : $encoding;
        // $diff = strlen($input) - (strlen($input) + mb_strlen($input, $encoding)) / 2;
        // return str_pad($input, $pad_length + $diff, $pad_string, $pad_type);

        // https://github.com/viossat/arraytotexttable/blob/6b1af924478cb9c3a903269e304fff006fe0dbf4/src/ArrayToTextTable.php#L255
        $encoding = $encoding === null ? mb_internal_encoding() : $encoding;
        $pad_before = $pad_type === STR_PAD_BOTH || $pad_type === STR_PAD_LEFT;
        $pad_after = $pad_type === STR_PAD_BOTH || $pad_type === STR_PAD_RIGHT;
        $pad_length -= mb_strlen($input, $encoding) + static::countCJK($input);
        $target_length = $pad_before && $pad_after ? $pad_length / 2 : $pad_length;

        $repeat_times = ceil($target_length / mb_strlen($pad_string, $encoding));
        $repeated_string = str_repeat($pad_string, max(0, (int)$repeat_times));
        $before = $pad_before ? mb_substr($repeated_string, 0, (int)floor($target_length), $encoding) : '';
        $after = $pad_after ? mb_substr($repeated_string, 0, (int)ceil($target_length), $encoding) : '';

        return $before . $input . $after;
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function setData(array $data = []): static
    {
        if (!is_array($data)) {
            $data = [];
        }
        $arrayData = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $arrayData[] = $row;
            } else if (is_object($row)) {
                $arrayData[] = get_object_vars($row);
            }
        }
        $this->data = $arrayData;
        return $this;
    }

    /**
     * @param string $string
     * @param string $color
     * @return string
     */
    protected function asciiColorWrap(string $string, string $color): string
    {
//        30: 黑
//        31: 红
//        32: 绿
//        33: 黄
//        34: 蓝
//        35: 紫
//        36: 深绿
//        37: 白色
        return "\033[1;" . $color . 'm' . $string . "\033[0m";
    }


    /**
     * 数组转表格
     * @param array $data
     * @param string|null $title
     * @param bool $print
     * @return array
     */
    public static function array2table(array $data, ?string $title = null, bool $print = false): array
    {
        $renderer = new self($data);
        $renderer->setTitle($title);
        $table = rtrim($renderer->getTable());
        $th_list = $table === '' ? [] : (preg_split('/\R/u', $table) ?: []);

        if ($print) {
            foreach ($th_list as $value) {
                echo $value . PHP_EOL;
            }
        }

        return $th_list;
    }

    protected static function displayWidth(string $value): int
    {
        return (int)((strlen($value) + mb_strlen($value, 'UTF-8')) / 2);
    }

    protected static function padDisplay(string $value, int $width, int $alignment): string
    {
        $visible = self::displayWidth($value);
        $padding = max(0, $width - $visible);
        if ($alignment === STR_PAD_LEFT) {
            return str_repeat(' ', $padding) . $value;
        }

        if ($alignment === STR_PAD_BOTH) {
            $left = intdiv($padding, 2);
            $right = $padding - $left;
            return str_repeat(' ', $left) . $value . str_repeat(' ', $right);
        }

        return $value . str_repeat(' ', $padding);
    }

    /**
     * @return void
     */
    protected function expandWidthsForTitle(): void
    {
        if ($this->title === null || $this->title === '' || $this->keys === []) {
            return;
        }

        $requiredInnerWidth = self::displayWidth($this->title) + 2;
        $currentInnerWidth = $this->innerWidth();
        if ($requiredInnerWidth <= $currentInnerWidth) {
            return;
        }

        $lastKey = end($this->keys);
        if ($lastKey === false) {
            return;
        }

        $this->widths[$lastKey] = ($this->widths[$lastKey] ?? 0) + ($requiredInnerWidth - $currentInnerWidth);
    }

    protected function innerWidth(): int
    {
        if ($this->keys === []) {
            return $this->title === null ? 0 : self::displayWidth($this->title) + 2;
        }

        return array_sum($this->widths) + count($this->keys) * 3 - 1;
    }

    protected function renderTitleOnlyTable(string $indentation): string
    {
        if ($this->title === null || $this->title === '') {
            return '';
        }

        $width = self::displayWidth($this->title) + 2;
        return $indentation . '┌' . str_repeat('─', $width) . '┐' . PHP_EOL
            . $indentation . '│ ' . $this->title . ' │' . PHP_EOL
            . $indentation . '└' . str_repeat('─', $width) . '┘' . PHP_EOL;
    }
}
