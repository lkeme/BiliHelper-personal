<?php
namespace Ds;

/**
 * Collection is the base interface which covers functionality common to all the
 * data structures in this library. It guarantees that all structures are
 * traversable, countable, and can be converted to json using json_encode().
 *
 * @package Ds
 *
 * @template-covariant TKey
 * @template-covariant TValue
 * @extends \IteratorAggregate<TKey, TValue>
 */
interface Collection extends \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * Removes all values from the collection.
     */
    public function clear();

    /**
     * Returns the size of the collection.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Returns a shallow copy of the collection.
     *
     * @return static a copy of the collection.
     *
     * @psalm-return static<TKey, TValue>
     */
    public function copy();

    /**
     * Returns whether the collection is empty.
     *
     * This should be equivalent to a count of zero, but is not required.
     * Implementations should define what empty means in their own context.
     */
    public function isEmpty(): bool;

    /**
     * Returns an array representation of the collection.
     *
     * The format of the returned array is implementation-dependent.
     * Some implementations may throw an exception if an array representation
     * could not be created.
     *
     * @return array<TKey, TValue>
     */
    public function toArray(): array;
}
