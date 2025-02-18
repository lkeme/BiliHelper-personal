<?php
namespace Ds\Traits;

use Ds\Sequence;
use OutOfRangeException;
use UnderflowException;

/**
 * Common functionality of all structures that implement 'Sequence'. Because the
 * polyfill's only goal is to achieve consistent behaviour, all sequences will
 * share the same implementation using an array array.
 *
 * @package Ds\Traits
 *
 * @template TValue
 */
trait GenericSequence
{
    /**
     * @var array internal array used to store the values of the sequence.
     *
     * @psalm-var array<TValue>
     */
    private $array = [];

    /**
     * @param iterable $values
     *
     * @psalm-param iterable<TValue> $values
     */
    public function __construct(iterable $values = [])
    {
        foreach ($values as $value) {
            $this->push($value);
        }

        $this->capacity = max(
            $values === null ? 0 : count($values),
            $this::MIN_CAPACITY
        );
    }

    /**
     * @return list<TValue>
     */
    public function toArray(): array
    {
        return $this->array;
    }

    /**
     * @psalm-param callable(TValue): TValue $callback
     */
    public function apply(callable $callback)
    {
        foreach ($this->array as &$value) {
            $value = $callback($value);
        }
    }

    /**
     * @template TValue2
     * @psalm-param iterable<TValue2> $values
     * @psalm-return Sequence<TValue|TValue2>
     */
    public function merge($values): Sequence
    {
        $copy = $this->copy();
        $copy->push(...$values);
        return $copy;
    }

    /**
     *
     */
    public function count(): int
    {
        return count($this->array);
    }

    /**
     * @psalm-param TValue ...$values
     */
    public function contains(...$values): bool
    {
        foreach ($values as $value) {
            if ($this->find($value) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @psalm-param (callable(TValue): bool)|null $callback
     * @psalm-return Sequence<TValue>
     */
    public function filter(callable|null $callback = null): Sequence
    {
        return new self(array_filter($this->array, $callback ?: 'boolval'));
    }

    /**
     * @return int|null
     *
     * @psalm-param TValue $value
     */
    public function find($value)
    {
        $offset = array_search($value, $this->array, true);

        return $offset === false ? null : $offset;
    }

    /**
     * @throws \UnderflowException if the sequence is empty.
     *
     * @psalm-return TValue
     */
    public function first()
    {
        if ($this->isEmpty()) {
            throw new UnderflowException();
        }

        return $this->array[0];
    }

    /**
     * @throws \OutOfRangeException if the index is not in the range [0, size-1]
     *
     * @psalm-return TValue
     */
    public function get(int $index)
    {
        if ( ! $this->validIndex($index)) {
            throw new OutOfRangeException();
        }

        return $this->array[$index];
    }

    /**
     * @throws \OutOfRangeException if the index is not in the range [0, n]
     *
     * @psalm-param TValue ...$values
     */
    public function insert(int $index, ...$values)
    {
        if ( ! $this->validIndex($index) && $index !== count($this)) {
            throw new OutOfRangeException();
        }

        array_splice($this->array, $index, 0, $values);
        $this->checkCapacity();
    }

    /**
     *
     */
    public function join(string $glue = null): string
    {
        return implode($glue ?? '', $this->array);
    }

    /**
     * @throws \UnderflowException if the sequence is empty.
     *
     * @psalm-return TValue
     */
    public function last()
    {
        if ($this->isEmpty()) {
            throw new UnderflowException();
        }

        return $this->array[count($this) - 1];
    }

    /**
     * @template TNewValue
     * @psalm-param callable(TValue): TNewValue $callback
     * @psalm-return Sequence<TNewValue>
     */
    public function map(callable $callback): Sequence
    {
        return new self(array_map($callback, $this->array));
    }

    /**
     * @throws \UnderflowException if the sequence is empty.
     * @psalm-return TValue
     */
    public function pop()
    {
        if ($this->isEmpty()) {
            throw new UnderflowException();
        }

        $value = array_pop($this->array);
        $this->checkCapacity();

        return $value;
    }

    /**
     * @psalm-param TValue ...$values
     */
    public function push(...$values)
    {
        $this->ensureCapacity($this->count() + count($values));

        foreach ($values as $value) {
            $this->array[] = $value;
        }
    }

    /**
     * @template TCarry
     * @psalm-param callable(TCarry, TValue): TCarry $callback
     * @psalm-param TCarry $initial
     * @psalm-return TCarry
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->array, $callback, $initial);
    }

    /**
     * @throws \OutOfRangeException if the index is not in the range [0, size-1]
     *
     * @psalm-return TValue
     */
    public function remove(int $index)
    {
        if ( ! $this->validIndex($index)) {
            throw new OutOfRangeException();
        }

        $value = array_splice($this->array, $index, 1, null)[0];
        $this->checkCapacity();

        return $value;
    }

    /**
     *
     */
    public function reverse()
    {
        $this->array = array_reverse($this->array);
    }

    /**
     * @psalm-return Sequence<TValue>
     */
    public function reversed(): Sequence
    {
        return new self(array_reverse($this->array));
    }

    /**
     * Converts negative or large rotations into the minimum positive number
     * of rotations required to rotate the sequence by a given $r.
     */
    private function normalizeRotations(int $r)
    {
        $n = count($this);

        if ($n < 2) return 0;
        if ($r < 0) return $n - (abs($r) % $n);

        return $r % $n;
    }

    /**
     *
     */
    public function rotate(int $rotations)
    {
        for ($r = $this->normalizeRotations($rotations); $r > 0; $r--) {
            array_push($this->array, array_shift($this->array));
        }
    }

    /**
     * @throws \OutOfRangeException if the index is not in the range [0, size-1]
     *
     * @psalm-param TValue $value
     */
    public function set(int $index, $value)
    {
        if ( ! $this->validIndex($index)) {
            throw new OutOfRangeException();
        }

        $this->array[$index] = $value;
    }

    /**
     * @throws \UnderflowException if the sequence was empty.
     *
     * @psalm-return TValue
     */
    public function shift()
    {
        if ($this->isEmpty()) {
            throw new UnderflowException();
        }

        $value = array_shift($this->array);
        $this->checkCapacity();

        return $value;
    }

    /**
     * @psalm-return Sequence<TValue>
     */
    public function slice(int $offset, int $length = null): Sequence
    {
        if (func_num_args() === 1) {
            $length = count($this);
        }

        return new self(array_slice($this->array, $offset, $length));
    }

    /**
     * @psalm-param (callable(TValue, TValue): int)|null $comparator
     */
    public function sort(callable $comparator = null)
    {
        if ($comparator) {
            usort($this->array, $comparator);
        } else {
            sort($this->array);
        }
    }

    /**
     * @psalm-param (callable(TValue, TValue): int)|null $comparator
     * @psalm-return Sequence<TValue>
     */
    public function sorted(callable $comparator = null): Sequence
    {
        $copy = $this->copy();
        $copy->sort($comparator);
        return $copy;
    }

    /**
     * @return int|float
     */
    public function sum()
    {
        return array_sum($this->array);
    }

    /**
     * @psalm-param TValue ...$values
     */
    public function unshift(...$values)
    {
        if ($values) {
            $this->array = array_merge($values, $this->array);
            $this->checkCapacity();
        }
    }

    /**
     *
     */
    private function validIndex(int $index)
    {
        return $index >= 0 && $index < count($this);
    }

    /**
     *
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        foreach ($this->array as $value) {
            yield $value;
        }
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        $this->array = [];
        $this->capacity = self::MIN_CAPACITY;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            $this->push($value);
        } else {
            $this->set($offset, $value);
        }
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function &offsetGet($offset)
    {
        if ( ! $this->validIndex($offset)) {
            throw new OutOfRangeException();
        }

        return $this->array[$offset];
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        if (is_integer($offset) && $this->validIndex($offset)) {
            $this->remove($offset);
        }
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return is_integer($offset)
            && $this->validIndex($offset)
            && $this->get($offset) !== null;
    }
}
