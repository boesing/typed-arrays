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
 */
interface ArrayInterface extends IteratorAggregate, Countable
{
    /**
     * @psalm-param TValue $element
     * @psalm-mutation-free
     */
    public function contains($element): bool;

    /**
     * @psalm-return TValue
     * @psalm-mutation-free
     * @throws OutOfBoundsException if there are no values available.
     */
    public function first();

    /**
     * @psalm-return TValue
     * @psalm-mutation-free
     * @throws OutOfBoundsException if there are no values available.
     */
    public function last();

    /**
     * @psalm-mutation-free
     */
    public function isEmpty(): bool;

    /**
     * @psalm-return array<TKey,TValue>
     * @psalm-mutation-free
     */
    public function toNativeArray(): array;
}
