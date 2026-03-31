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

namespace Bhp\Util\Resource;

use ArrayAccess;
use Countable;
use RecursiveArrayIterator;
use Traversable;
use function is_array;

class Collection implements ArrayAccess, Countable, \IteratorAggregate
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    public int $mergeDepth = 3;
    public string $keyPathSep = '.';

    public function clear(): static
    {
        $this->data = [];

        return $this;
    }

    public function set(string $key, mixed $value): static
    {
        if ($this->keyPathSep !== '' && strpos($key, $this->keyPathSep) > 0) {
            $this->setByPath($this->data, $key, $value, $this->keyPathSep);

            return $this;
        }

        $this->data[$key] = $value;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->keyPathSep !== '' && strpos($key, $this->keyPathSep) > 0) {
            return $this->getByPath($this->data, $key, $default, $this->keyPathSep);
        }

        return $this->data[$key] ?? $default;
    }

    public function getInt(string $key, mixed $default = null): int
    {
        return (int)$this->get($key, $default);
    }

    public function getString(string $key, mixed $default = null): string
    {
        $value = $this->get($key, $default);

        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        return '';
    }

    public function getBool(string $key, mixed $default = null): bool
    {
        $value = $this->get($key, $default);

        return match (true) {
            is_bool($value) => $value,
            is_string($value) => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            default => (bool)$value,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getArray(string $key, mixed $default = null): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : (is_array($default) ? $default : []);
    }

    public function exists(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function has(string $key): bool
    {
        return $this->exists($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArrayCopy(): array
    {
        return $this->data;
    }

    public function getKeyPathSep(): string
    {
        return $this->keyPathSep;
    }

    public function setKeyPathSep(string $keyPathSep): void
    {
        $this->keyPathSep = $keyPathSep;
    }

    public function load(array|Traversable $data): self
    {
        $this->bindData($this->data, $data);

        return $this;
    }

    public function loadData(array|Traversable $data): self
    {
        $this->bindData($this->data, $data);

        return $this;
    }

    /**
     * @param array<string, mixed> $parent
     */
    protected function bindData(array &$parent, array|Traversable $data, int $depth = 1): void
    {
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value) && isset($parent[$key]) && is_array($parent[$key])) {
                if ($depth > $this->mergeDepth) {
                    $parent[$key] = $value;
                } else {
                    $nextDepth = $depth + 1;
                    $this->bindData($parent[$key], $value, $nextDepth);
                }
            } else {
                $parent[$key] = $value;
            }
        }
    }

    /**
     * @return string[]
     */
    public function getKeys(): array
    {
        return array_keys($this->data);
    }

    public function getIterator(): Traversable
    {
        return new RecursiveArrayIterator($this->data);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[(string)$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string)$offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string)$offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->set((string)$offset, null);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function __clone()
    {
        $copy = unserialize(serialize($this->data));
        $this->data = is_array($copy) ? $copy : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function getByPath(array $data, string $path, mixed $default = null, string $separator = '.'): mixed
    {
        $segments = explode($separator, $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function setByPath(array &$data, string $path, mixed $value, string $separator = '.'): void
    {
        $segments = explode($separator, $path);
        $current = &$data;

        foreach ($segments as $index => $segment) {
            $last = $index === array_key_last($segments);
            if ($last) {
                $current[$segment] = $value;
                break;
            }

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }
    }
}
