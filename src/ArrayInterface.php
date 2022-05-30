<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use Countable;
use IteratorAggregate;
use OutOfBoundsException;

/**
 * @template         TKey of array-key
 * @template         TValue
 * @template-extends IteratorAggregate<TKey,TValue>
 * @psalm-immutable
 */
interface ArrayInterface extends IteratorAggregate, Countable
{
    /**
     * Verifies if an element is within the item storage.
     *
     * @psalm-param TValue $element
     */
    public function contains($element): bool;

    /**
     * Returns the very first item.
     * This method is an equivalent of the `reset` function - it just does not return `false` but throws an exception
     * in case there are no items stored.
     *
     * @psalm-return TValue
     * @throws OutOfBoundsException if there are no values available.
     */
    public function first();

    /**
     * Returns the very last item.
     * This method is an equivalent of the `end` function - it just does not return `false` but throws an exception
     * there are no items stored.
     *
     * @psalm-return TValue
     * @throws OutOfBoundsException if there are no values available.
     */
    public function last();

    /**
     * Returns `true` in case there are no items stored.
     */
    public function isEmpty(): bool;

    /**
     * Tests if all elements satisfy the given predicate.
     *
     * @psalm-param pure-callable(TValue):bool $callback
     */
    public function allSatisfy(callable $callback): bool;

    /**
     * Tests for the existence of an element that satisfies the given predicate.
     *
     * @psalm-param pure-callable(TValue):bool $callback
     */
    public function exists(callable $callback): bool;

    /**
     * Returns the amount of items.
     *
     * @return 0|positive-int
     */
    public function count(): int;
}
