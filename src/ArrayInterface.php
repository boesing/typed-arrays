<?php
declare(strict_types=1);

namespace Boesing\TypedArrays;

use Countable;
use IteratorAggregate;

/**
 * @template         TKey of array-key
 * @template         TValue
 * @template-extends IteratorAggregate<TKey,TValue>
 * @internal
 */
interface ArrayInterface extends IteratorAggregate, Countable
{
    /**
     * @psalm-param TValue $element
     */
    public function contains($element): bool;

    /**
     * @psalm-return TValue|null
     */
    public function first();

    /**
     * @psalm-return TValue|null
     */
    public function last();

    public function isEmpty(): bool;

    /**
     * @psalm-return array<TKey,TValue>
     */
    public function toNativeArray(): array;
}
