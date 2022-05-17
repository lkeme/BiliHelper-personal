<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 *  Source: EasySwoole\Utility\ArrayToTextTable;
 */

namespace BiliHelper\Tool;

use Closure;

class ArrayToTextTable
{
    const AlignLeft = STR_PAD_RIGHT;
    const AlignCenter = STR_PAD_BOTH;
    const AlignRight = STR_PAD_LEFT;

    protected array $data;
    protected array $keys;
    protected array $widths;
    protected string $indentation;
    protected bool $displayHeader = true;
    protected int $keysAlignment;
    protected int $valuesAlignment;
    protected mixed $formatter;

    public function __construct($data = [])
    {
        $this->setData($data)
            ->setIndentation('')
            ->setKeysAlignment(self::AlignCenter)
            ->setValuesAlignment(self::AlignLeft)
            ->setFormatter(null);
    }

    public function __toString()
    {
        return $this->getTable();
    }

    public function getTable($data = null): string
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

    public function setIndentation($indentation): static
    {
        $this->indentation = $indentation;
        return $this;
    }

    public function isDisplayHeader(bool $displayHeader): static
    {
        $this->displayHeader = $displayHeader;
        return $this;
    }

    public function setKeysAlignment($keysAlignment): static
    {
        $this->keysAlignment = $keysAlignment;
        return $this;
    }

    public function setValuesAlignment($valuesAlignment): static
    {
        $this->valuesAlignment = $valuesAlignment;
        return $this;
    }

    public function setFormatter($formatter): static
    {
        $this->formatter = $formatter;
        return $this;
    }

    private function line($left, $horizontal, $link, $right): string
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

    private function row($row, $alignment): string
    {
        $line = '│';
        foreach ($this->keys as $key) {
            $value = $row[$key] ?? '';
            $line .= ' ' . static::mb_str_pad($value, $this->widths[$key], ' ', $alignment) . ' ' . '│';
        }
        if (empty($row)) {
            $line .= '│';
        }
        return $line;
    }

    private function prepare(): array
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

    private function setWidth($key, $value): void
    {
        if (!isset($this->widths[$key])) {
            $this->widths[$key] = 0;
        }
        // Deprecated: strlen(): Passing null to parameter #1 ($string) of type string is deprecated
        // Deprecated: mb_strlen(): Passing null to parameter #1 ($string) of type string is deprecated
        $value = $value ?: '';
        $width = (strlen($value) + mb_strlen($value, 'UTF8')) / 2;
        if ($width > $this->widths[$key]) {
            $this->widths[$key] = $width;
        }
    }

    private static function countCJK($string): bool|int|null
    {
        return preg_match_all('/[\p{Han}\p{Katakana}\p{Hiragana}\p{Hangul}]/u', $string);
    }

    private function mb_str_pad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT, $encoding = null): string
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
        $repeated_string = str_repeat($pad_string, max(0, $repeat_times));
        $before = $pad_before ? mb_substr($repeated_string, 0, floor($target_length), $encoding) : '';
        $after = $pad_after ? mb_substr($repeated_string, 0, ceil($target_length), $encoding) : '';

        return $before . $input . $after;

    }

    private function setData($data): static
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
}