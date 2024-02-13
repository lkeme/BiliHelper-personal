<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2024 ~ 2025
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
use IteratorAggregate;
use JsonSerializable;

/**
 * Collection Interface
 */
interface CollectionInterface extends ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    public function set(string $key, mixed $value): void;

    public function get(string $key, $default = null): mixed;

    /**
     * @param array $items
     */
    public function replace(array $items): void;

    /**
     * @return array
     */
    public function all(): array;

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function remove(string $key): void;

    /**
     * clear all data
     */
    public function clear(): void;
}
