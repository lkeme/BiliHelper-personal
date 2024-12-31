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

use RecursiveArrayIterator;
use Toolkit\Stdlib\Arr;
use Traversable;
use function is_array;
use function serialize;
use function strpos;
use function unserialize;

/**
 * Class Collection -> https://github.com/phppkg/config
 *
 * @package PhpPkg\Config
 *
 * 支持 链式的子节点 设置 和 值获取
 * e.g:
 * ```
 * $data = [
 *      'foo' => [
 *          'bar' => [
 *              'yoo' => 'value'
 *          ]
 *       ]
 * ];
 * $config = new Collection();
 * $config->get('foo.bar.yoo')` equals to $data['foo']['bar']['yoo'];
 * ```
 */
class Collection extends \Toolkit\Stdlib\Std\Collection
{
    /**
     * @var int
     */
    public int $mergeDepth = 3;

    /**
     * The key path separator.
     *
     * @var  string
     */
    public string $keyPathSep = '.';

    /**
     * set config value by key/path
     *
     * @param string $key
     * @param mixed $value
     *
     * @return mixed
     */
    public function set(string $key, mixed $value): self
    {
        if ($this->keyPathSep && strpos($key, $this->keyPathSep) > 0) {
            Arr::setByPath($this->data, $key, $value, $this->keyPathSep);
            return $this;
        }

        return parent::set($key, $value);
    }

    /**
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->keyPathSep && strpos($key, $this->keyPathSep) > 0) {
            return Arr::getByPath($this->data, $key, $default, $this->keyPathSep);
        }

        return parent::get($key, $default);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function exists(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->exists($key);
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getKeyPathSep(): string
    {
        return $this->keyPathSep;
    }

    /**
     * @param string $keyPathSep
     */
    public function setKeyPathSep(string $keyPathSep): void
    {
        $this->keyPathSep = $keyPathSep;
    }

    /**
     * @param array|Traversable $data
     *
     * @return $this
     */
    public function load(array|Traversable $data): self
    {
        $this->bindData($this->data, $data);

        return $this;
    }

    /**
     * @param array|Traversable $data
     *
     * @return $this
     */
    public function loadData(array|Traversable $data): self
    {
        $this->bindData($this->data, $data);

        return $this;
    }

    /**
     * @param array $parent
     * @param array|Traversable $data
     * @param int $depth
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
                    $this->bindData($parent[$key], $value, ++$depth);
                }
            } else {
                $parent[$key] = $value;
            }
        }
    }

    /**
     * @return array
     */
    public function getKeys(): array
    {
        return array_keys($this->data);
    }

    /**
     * @return RecursiveArrayIterator
     */
    public function getIterator(): Traversable
    {
        return new RecursiveArrayIterator($this->data);
    }

    /**
     * Unset an offset in the iterator.
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        $this->set($offset, null);
    }

    public function __clone()
    {
        $this->data = unserialize(serialize($this->data), [
            'allowed_classes' => self::class
        ]);
    }
}
