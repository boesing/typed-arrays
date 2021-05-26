<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use Error;
use InvalidArgumentException;
use JsonSerializable;
use OutOfBoundsException;

/**
 * @template         TValue
 * @template-extends ArrayInterface<int,TValue>
 * @psalm-immutable
 */
interface OrderedListInterface extends ArrayInterface, JsonSerializable
{
    /**
     * @psalm-param TValue $element
     * @psalm-return OrderedListInterface<TValue>
     */
    public function add($element): OrderedListInterface;

    /**
     * @psalm-return TValue
     * @throws OutOfBoundsException If position does not exist.
     */
    public function at(int $position);

    /**
     * @psalm-param  pure-callable(TValue,int):bool $callback
     * @psalm-return OrderedListInterface<TValue>
     */
    public function filter(callable $callback): OrderedListInterface;

    /**
     * @psalm-param  (pure-callable(TValue,TValue):int)|null $callback
     * @psalm-return OrderedListInterface<TValue>
     */
    public function sort(?callable $callback = null): OrderedListInterface;

    /**
     * @psalm-param  list<OrderedListInterface<TValue>> $stack
     * @psalm-return OrderedListInterface<TValue>
     */
    public function merge(...$stack): OrderedListInterface;

    /**
     * @template     TNewValue
     * @psalm-param  pure-callable(TValue,int):TNewValue $callback
     * @psalm-return OrderedListInterface<TNewValue>
     */
    public function map(callable $callback): OrderedListInterface;

    /**
     * @psalm-param  OrderedListInterface<TValue> $other
     * @psalm-param  (pure-callable(TValue,TValue):int)|null $valueComparator
     * @psalm-return OrderedListInterface<TValue>
     */
    public function intersect(OrderedListInterface $other, ?callable $valueComparator = null): OrderedListInterface;

    /**
     * @psalm-param  OrderedListInterface<TValue> $other
     * @psalm-param  (pure-callable(TValue,TValue):int)|null $valueComparator
     * @psalm-return OrderedListInterface<TValue>
     */
    public function diff(OrderedListInterface $other, ?callable $valueComparator = null): OrderedListInterface;

    /**
     * @template TKey of non-empty-string
     * @psalm-param  pure-callable(TValue):TKey $keyGenerator
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function toMap(callable $keyGenerator): MapInterface;

    /**
     * @psalm-param  TValue $element
     * @psalm-return OrderedListInterface<TValue>
     */
    public function removeElement($element): OrderedListInterface;

    /**
     * @psalm-param (pure-callable(TValue):non-empty-string)|null $unificationIdentifierGenerator
     * @psalm-param (pure-callable(TValue,TValue):TValue)|null  $callback
     * @psalm-return OrderedListInterface<TValue>
     */
    public function unify(
        ?callable $unificationIdentifierGenerator = null,
        ?callable $callback = null
    ): OrderedListInterface;

    /**
     * @throws InvalidArgumentException if start index does is not fitting in the current list state.
     *
     * @psalm-param TValue|callable(int):TValue $value
     * @psalm-return OrderedListInterface<TValue>
     */
    public function fill(int $startIndex, int $amount, $value): OrderedListInterface;

    /**
     * @psalm-return OrderedListInterface<TValue>
     */
    public function slice(int $offset, ?int $length = null): OrderedListInterface;

    /**
     * @psalm-param pure-callable(TValue):bool $callback
     * @psalm-return TValue
     * @throws OutOfBoundsException if value could not be found with provided callback.
     */
    public function find(callable $callback);

    /**
     * Partitions the current list into those items which are filtered by the callback and those which don't.
     *
     * @param pure-callable(TValue $value):bool $callback
     *
     * @psalm-return array{0:OrderedListInterface<TValue>,1:OrderedListInterface<TValue>}
     */
    public function partition(callable $callback): array;

    /**
     * @template TGroup of non-empty-string
     * @psalm-param pure-callable(TValue):TGroup $callback
     *
     * @psalm-return MapInterface<TGroup,OrderedListInterface<TValue>>
     */
    public function group(callable $callback): MapInterface;

    /**
     * @psalm-pure
     */
    public function has(int $index): bool;

    /**
     * @param pure-callable(TValue,int):void $callback
     * @throws OrderedErrorCollection If an error occured during execution.
     */
    public function forAll(callable $callback): ForAllPromiseInterface;

    /**
     * @psalm-return OrderedListInterface<TValue>
     */
    public function reverse(): self;

    /**
     * @psalm-param (pure-callable(TValue):string)|null $callback
     * @throws Error In case, the values are not `string` or {@see Stringable}.
     */
    public function join(string $separator = ''): string;
}
