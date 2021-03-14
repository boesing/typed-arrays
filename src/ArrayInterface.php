<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use Countable;
use IteratorAggregate;
use OutOfBoundsException;

/**
 * @internal
 *
 * @template         TKey of array-key
 * @template         TValue
 * @template-extends IteratorAggregate<TKey,TValue>
 * @psalm-immutable
 */
interface ArrayInterface extends IteratorAggregate, Countable
{
    /**
     * @psalm-param TValue $element
     */
    public function contains($element): bool;

    /**
     * @psalm-return TValue
     * @throws OutOfBoundsException if there are no values available.
     */
    public function first();

    /**
     * @psalm-return TValue
     * @throws OutOfBoundsException if there are no values available.
     */
    public function last();

    public function isEmpty(): bool;

    /**
     * @psalm-return array<TKey,TValue>
     */
    public function toNativeArray(): array;

    /**
     * Tests if all elements satisfy the given predicate.
     *
     * @psalm-param callable(TValue):bool $callback
     */
    public function allSatisfy(callable $callback): bool;

    /**
     * Tests for the existence of an element that satisfies the given predicate.
     *
     * @psalm-param callable(TValue):bool $callback
     */
    public function exists(callable $callback): bool;
}
