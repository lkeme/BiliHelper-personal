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

namespace Bhp\Util\AsciiTable;

use AsciiTable\Builder;
use AsciiTable\Exception\BuilderException;
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
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setData($data)
            ->setIndentation('')
            ->setKeysAlignment(self::AlignCenter)
            ->setValuesAlignment(self::AlignLeft)
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

        $i = $this->indentation;

        $table = $i . $this->line('┌', '─', '┬', '┐') . PHP_EOL;

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
     * 数组转表格(外置)
     * @param array $data
     * @param string|null $title
     * @param bool $print
     * @return array
     */
    public static function array2table(array $data, ?string $title = null, bool $print = false): array
    {
        $th_list = [];
        //
        $builder = new Builder();
        $builder->addRows($data);
        //
        if (!is_null($title)) {
            $builder->setTitle($title);
        }
        //
        foreach (explode("\r\n", $builder->renderTable()) as $value) {
            if ($value) {
                $th_list[] = $value;
                if ($print) echo $value . PHP_EOL;
            }
        }
        return $th_list;
    }
}
