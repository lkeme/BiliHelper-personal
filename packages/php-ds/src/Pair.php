<?php
namespace Ds;

use OutOfBoundsException;

/**
 * A pair which represents a key and an associated value.
 *
 * @property mixed $key
 * @property mixed $value
 *
 * @package Ds
 *
 * @template-covariant TKey
 * @template-covariant TValue
 */
final class Pair implements \JsonSerializable
{
    /**
     * @var mixed The pair's key
     *
     * @psalm-param TKey $key
     */
    public $key;

    /**
     * @var mixed The pair's value
     *
     * @psalm-param TValue $value
     */
    public $value;

    /**
     * Creates a new instance.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @psalm-param TKey $key
     * @psalm-param TValue $value
     */
    public function __construct($key = null, $value = null)
    {
        $this->key   = $key;
        $this->value = $value;
    }

    /**
     *
     * @param mixed $name
     *
     * @return mixed|null
     */
    public function __isset($name)
    {
        if ($name === 'key' || $name === 'value') {
            return $this->$name !== null;
        }
        return false;
    }

    /**
     * This allows unset($pair->key) to not completely remove the property,
     * but be set to null instead.
     *
     * @return void
     */
    public function __unset(string $name)
    {
        if ($name === 'key' || $name === 'value') {
            $this->$name = null;
            return;
        }
        throw new OutOfBoundsException();
    }

    /**
     * @param mixed $name
     *
     * @return mixed|null
     */
    public function &__get($name)
    {
        if ($name === 'key' || $name === 'value') {
            return $this->$name;
        }
        throw new OutOfBoundsException();
    }

    /**
     * @param mixed $name
     * @param mixed $value
     *
     * @return mixed|null
     */
    public function __set($name, $value)
    {
        if ($name === 'key' || $name === 'value') {
            $this->$name = $value;
            return;
        }
        throw new OutOfBoundsException();
    }

    /**
     * Returns a copy of the Pair
     *
     * @psalm-return self<TKey, TValue>
     */
    public function copy(): self
    {
        return new self($this->key, $this->value);
    }

    /**
     * Returns a representation to be used for var_dump and print_r.
     *
     * @return array
     *
     * @psalm-return array{key: TKey, value: TValue}
     */
    public function __debugInfo()
    {
        return $this->toArray();
    }

    /**
     * @inheritDoc
     *
     * @psalm-return array{key: TKey, value: TValue}
     */
    public function toArray(): array
    {
        return [
            'key'   => $this->key,
            'value' => $this->value,
        ];
    }

    /**
     * @inheritDoc
     *
     * @psalm-return array{key: TKey, value: TValue}
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Returns a string representation of the pair.
     */
    public function __toString()
    {
        return 'object(' . get_class($this) . ')';
    }
}
