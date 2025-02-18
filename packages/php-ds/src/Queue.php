<?php
namespace Ds;

use Error;
use OutOfBoundsException;

/**
 * A “first in, first out” or “FIFO” collection that only allows access to the
 * value at the front of the queue and iterates in that order, destructively.
 *
 * @package Ds
 *
 * @template TValue
 * @implements Collection<int, TValue>
 * @implements \ArrayAccess<int, TValue>
 * @template-use Traits\GenericCollection<int, TValue>
 */
final class Queue implements Collection, \ArrayAccess 
{
    use Traits\GenericCollection;

    /**
     * @var Deque internal deque to store values.
     *
     * @psalm-var Deque<TValue>
     */
    private $deque;

    /**
     * Creates an instance using the values of an array or Traversable object.
     *
     * @param iterable<mixed> $values
     *
     * @psalm-param iterable<TValue> $values
     */
    public function __construct(iterable $values = [])
    {
        $this->deque = new Deque($values);
    }

    /**
     * Ensures that enough memory is allocated for a specified capacity. This
     * potentially reduces the number of reallocations as the size increases.
     *
     * @param int $capacity The number of values for which capacity should be
     *                      allocated. Capacity will stay the same if this value
     *                      is less than or equal to the current capacity.
     */
    public function allocate(int $capacity)
    {
        $this->deque->allocate($capacity);
    }

    /**
     * Returns the current capacity of the queue.
     */
    public function capacity(): int
    {
        return $this->deque->capacity();
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        $this->deque->clear();
    }

    /**
     * @inheritDoc
     */
    public function copy(): self
    {
        return new self($this->deque);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->deque);
    }

    /**
     * Returns the value at the front of the queue without removing it.
     *
     * @return mixed
     *
     * @psalm-return TValue
     */
    public function peek()
    {
        return $this->deque->first();
    }

    /**
     * Returns and removes the value at the front of the Queue.
     *
     * @return mixed
     *
     * @psalm-return TValue
     */
    public function pop()
    {
        return $this->deque->shift();
    }

    /**
     * Pushes zero or more values into the back of the queue.
     *
     * @param mixed ...$values
     *
     * @psalm-param TValue ...$values
     */
    public function push(...$values)
    {
        $this->deque->push(...$values);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return $this->deque->toArray();
    }

    /**
     * Get iterator
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        while ( ! $this->isEmpty()) {
            yield $this->pop();
        }
    }


    /**
     * @inheritdoc
     *
     * @throws OutOfBoundsException
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            $this->push($value);
        } else {
            throw new Error();
        }
    }

    /**
     * @inheritdoc
     *
     * @throws Error
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        throw new Error();
    }

    /**
     * @inheritdoc
     *
     * @throws Error
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        throw new Error();
    }

    /**
     * @inheritdoc
     *
     * @throws Error
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        throw new Error();
    }

    /**
     * Ensures that the internal sequence will be cloned too.
     */
    public function __clone()
    {
        $this->deque = clone $this->deque;
    }
}
