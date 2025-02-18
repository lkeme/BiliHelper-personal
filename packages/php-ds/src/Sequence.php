<?php
namespace Ds;

/**
 * Describes the behaviour of values arranged in a single, linear dimension.
 * Some languages refer to this as a "List". It’s similar to an array that uses
 * incremental integer keys, with the exception of a few characteristics:
 *
 *  - Values will always be indexed as [0, 1, 2, …, size - 1].
 *  - Only allowed to access values by index in the range [0, size - 1].
 *
 * @package Ds
 *
 * @template TValue
 * @extends Collection<int, TValue>
 * @extends \ArrayAccess<int, TValue>
 */
interface Sequence extends Collection, \ArrayAccess
{
    /**
     * Ensures that enough memory is allocated for a required capacity.
     *
     * @param int $capacity The number of values for which capacity should be
     *                      allocated. Capacity will stay the same if this value
     *                      is less than or equal to the current capacity.
     */
    public function allocate(int $capacity);

    /**
     * Updates every value in the sequence by applying a callback, using the
     * return value as the new value.
     *
     * @param callable $callback Accepts the value, returns the new value.
     *
     * @psalm-param callable(TValue): TValue $callback
     */
    public function apply(callable $callback);

    /**
     * Returns the current capacity of the sequence.
     *
     * @return int
     */
    public function capacity(): int;

    /**
     * Determines whether the sequence contains all of zero or more values.
     *
     * @param mixed ...$values
     *
     * @return bool true if at least one value was provided and the sequence
     *              contains all given values, false otherwise.
     *
     * @psalm-param TValue ...$values
     */
    public function contains(...$values): bool;

    /**
     * Returns a new sequence containing only the values for which a callback
     * returns true. A boolean test will be used if a callback is not provided.
     *
     * @param callable|null $callback Accepts a value, returns a boolean result:
     *                                true : include the value,
     *                                false: skip the value.
     *
     * @return Sequence
     *
     * @psalm-param (callable(TValue): bool)|null $callback
     * @psalm-return Sequence<TValue>
     */
    public function filter(callable|null $callback = null): Sequence;

    /**
     * Returns the index of a given value, or null if it could not be found.
     *
     * @param mixed $value
     *
     * @return int|null
     *
     * @psalm-param TValue $value
     */
    public function find($value);

    /**
     * Returns the first value in the sequence.
     *
     * @return mixed
     *
     * @throws \UnderflowException if the sequence is empty.
     *
     * @psalm-return TValue
     */
    public function first();

    /**
     * Returns the value at a given index (position) in the sequence.
     *
     * @return mixed
     *
     * @throws \OutOfRangeException if the index is not in the range [0, size-1]
     *
     * @psalm-return TValue
     */
    public function get(int $index);

    /**
     * Inserts zero or more values at a given index.
     *
     * Each value after the index will be moved one position to the right.
     * Values may be inserted at an index equal to the size of the sequence.
     *
     * @param mixed ...$values
     *
     * @throws \OutOfRangeException if the index is not in the range [0, n]
     *
     * @psalm-param TValue ...$values
     */
    public function insert(int $index, ...$values);

    /**
     * Joins all values of the sequence into a string, adding an optional 'glue'
     * between them. Returns an empty string if the sequence is empty.
     */
    public function join(string $glue = null): string;

    /**
     * Returns the last value in the sequence.
     *
     * @return mixed
     *
     * @throws \UnderflowException if the sequence is empty.
     *
     * @psalm-return TValue
     */
    public function last();

    /**
     * Returns a new sequence using the results of applying a callback to each
     * value.
     *
     * @param callable $callback
     *
     * @return Sequence
     *
     * @template TNewValue
     * @psalm-param callable(TValue): TNewValue $callback
     * @psalm-return Sequence<TNewValue>
     */
    public function map(callable $callback): Sequence;

    /**
     * Returns the result of adding all given values to the sequence.
     *
     * @param array|\Traversable $values
     *
     * @return Sequence
     *
     * @template TValue2
     * @psalm-param iterable<TValue2> $values
     * @psalm-return Sequence<TValue|TValue2>
     */
    public function merge($values): Sequence;

    /**
     * Removes the last value in the sequence, and returns it.
     *
     * @return mixed what was the last value in the sequence.
     *
     * @throws \UnderflowException if the sequence is empty.
     *
     * @psalm-return TValue
     */
    public function pop();

    /**
     * Adds zero or more values to the end of the sequence.
     *
     * @param mixed ...$values
     *
     * @psalm-param TValue ...$values
     */
    public function push(...$values);

    /**
     * Iteratively reduces the sequence to a single value using a callback.
     *
     * @param callable $callback Accepts the carry and current value, and
     *                           returns an updated carry value.
     *
     * @param mixed|null $initial Optional initial carry value.
     *
     * @return mixed The carry value of the final iteration, or the initial
     *               value if the sequence was empty.
     *
     * @template TCarry
     * @psalm-param callable(TCarry, TValue): TCarry $callback
     * @psalm-param TCarry $initial
     * @psalm-return TCarry
     */
    public function reduce(callable $callback, $initial = null);

    /**
     * Removes and returns the value at a given index in the sequence.
     *
     * @param int $index this index to remove.
     *
     * @return mixed the removed value.
     *
     * @throws \OutOfRangeException if the index is not in the range [0, size-1]
     *
     * @psalm-return TValue
     */
    public function remove(int $index);

    /**
     * Reverses the sequence in-place.
     */
    public function reverse();

    /**
     * Returns a reversed copy of the sequence.
     *
     * @return Sequence
     *
     * @psalm-return Sequence<TValue>
     */
    public function reversed();

    /**
     * Rotates the sequence by a given number of rotations, which is equivalent
     * to successive calls to 'shift' and 'push' if the number of rotations is
     * positive, or 'pop' and 'unshift' if negative.
     *
     * @param int $rotations The number of rotations (can be negative).
     */
    public function rotate(int $rotations);

    /**
     * Replaces the value at a given index in the sequence with a new value.
     *
     * @param mixed $value
     *
     * @throws \OutOfRangeException if the index is not in the range [0, size-1]
     *
     * @psalm-param TValue $value
     */
    public function set(int $index, $value);

    /**
     * Removes and returns the first value in the sequence.
     *
     * @return mixed what was the first value in the sequence.
     *
     * @throws \UnderflowException if the sequence was empty.
     *
     * @psalm-return TValue
     */
    public function shift();

    /**
     * Returns a sub-sequence of a given length starting at a specified index.
     *
     * @param int $index  If the index is positive, the sequence will start
     *                    at that index in the sequence. If index is negative,
     *                    the sequence will start that far from the end.
     *
     * @param int $length If a length is given and is positive, the resulting
     *                    sequence will have up to that many values in it.
     *                    If the length results in an overflow, only values
     *                    up to the end of the sequence will be included.
     *
     *                    If a length is given and is negative, the sequence
     *                    will stop that many values from the end.
     *
     *                    If a length is not provided, the resulting sequence
     *                    will contain all values between the index and the
     *                    end of the sequence.
     *
     * @return Sequence
     *
     * @psalm-return Sequence<TValue>
     */
    public function slice(int $index, int $length = null): Sequence;

    /**
     * Sorts the sequence in-place, based on an optional callable comparator.
     *
     * @param callable|null $comparator Accepts two values to be compared.
     *                                  Should return the result of a <=> b.
     *
     * @psalm-param (callable(TValue, TValue): int)|null $comparator
     */
    public function sort(callable $comparator = null);

    /**
     * Returns a sorted copy of the sequence, based on an optional callable
     * comparator. Natural ordering will be used if a comparator is not given.
     *
     * @param callable|null $comparator Accepts two values to be compared.
     *                                  Should return the result of a <=> b.
     *
     * @return Sequence
     *
     * @psalm-param (callable(TValue, TValue): int)|null $comparator
     * @psalm-return Sequence<TValue>
     */
    public function sorted(callable $comparator = null): Sequence;

    /**
     * Returns the sum of all values in the sequence.
     *
     * @return int|float The sum of all the values in the sequence.
     */
    public function sum();

    /**
     * @inheritDoc
     *
     * @return list<TValue>
     */
    function toArray(): array;

    /**
     * Adds zero or more values to the front of the sequence.
     *
     * @param mixed ...$values
     *
     * @psalm-param TValue ...$values
     */
    public function unshift(...$values);
}
