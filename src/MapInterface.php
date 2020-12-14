<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use JsonSerializable;
use OutOfBoundsException;

/**
 * @template         TKey of string
 * @template         TValue
 * @template-extends ArrayInterface<TKey,TValue>
 */
interface MapInterface extends ArrayInterface, JsonSerializable
{
    /**
     * @psalm-param  Closure(TValue,TKey):bool $callback
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function filter(callable $callback): MapInterface;

    /**
     * @psalm-param  (Closure(TValue,TValue):int)|null $callback
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function sort(?callable $callback = null): MapInterface;

    /**
     * @psalm-param  list<MapInterface<TKey,TValue>> $stack
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function merge(...$stack): MapInterface;

    /**
     * @psalm-param  MapInterface<TKey,TValue> $other
     * @psalm-param  (Closure(TKey,TKey):int)|null $keyComparator
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function diffKeys(MapInterface $other, ?callable $keyComparator = null): MapInterface;

    /**
     * @template     TNewValue
     * @psalm-param  Closure(TValue):TNewValue $callback
     * @psalm-return MapInterface<TKey,TNewValue>
     */
    public function map(callable $callback): MapInterface;

    /**
     * @psalm-param  MapInterface<TKey,TValue> $other
     * @psalm-param  (Closure(TValue,TValue):int)|null $valueComparator
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function intersect(MapInterface $other, ?callable $valueComparator = null): MapInterface;

    /**
     * @psalm-param  MapInterface<TKey,TValue> $other
     * @psalm-param  (Closure(TValue,TValue):int)|null $valueComparator
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function diff(MapInterface $other, ?callable $valueComparator = null): MapInterface;

    /**
     * @psalm-param (Closure(TValue,TValue):int)|null $sorter
     * @psalm-return OrderedListInterface<TValue>
     */
    public function toOrderedList(?callable $sorter = null): OrderedListInterface;

    /**
     * Should remove all exact matches of the provided element.
     *
     * @psalm-param  TValue $element
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function removeElement($element): MapInterface;

    /**
     * @psalm-param  TKey $key
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function unset($key): MapInterface;

    /**
     * @psalm-return OrderedListInterface<TKey>
     */
    public function keys(): OrderedListInterface;

    /**
     * @psalm-param TKey   $key
     * @psalm-param TValue $value
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function put($key, $value): MapInterface;

    /**
     * @psalm-param TKey $key
     * @psalm-return TValue
     * @throws OutOfBoundsException if key does not exist.
     */
    public function get(string $key);

    /**
     * @psalm-param MapInterface<TKey,TValue> $other
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-param  (Closure(TValue,TValue):int)|null $valueComparator
     */
    public function intersectAssoc(MapInterface $other, ?callable $valueComparator = null): MapInterface;

    /**
     * @psalm-param MapInterface<TKey,TValue> $other
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-param  (Closure(TKey,TKey):int)|null $keyComparator
     */
    public function intersectUsingKeys(MapInterface $other, ?callable $keyComparator = null): MapInterface;

    /**
     * @psalm-param MapInterface<TKey,TValue> $other
     * @psalm-param  (Closure(TValue,TValue):int)|null $valueComparator
     * @psalm-param  (Closure(TKey,TKey):int)|null $keyComparator
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function intersectUserAssoc(
        MapInterface $other,
        ?callable $valueComparator = null,
        ?callable $keyComparator = null
    ): MapInterface;

    /**
     * @psalm-param TKey $key
     */
    public function has(string $key): bool;

    /**
     * Partitions the current map into those items which are filtered by the callback and those which don't.
     *
     * @psalm-param Closure(TValue):bool $callback
     * @psalm-return array{0:MapInterface<TKey,TValue>,1:MapInterface<TKey,TValue>}
     */
    public function partition(callable $callback): array;

    /**
     * @template TGroup of non-empty-string
     * @psalm-param Closure(TValue):TGroup $callback
     *
     * @psalm-return MapInterface<TGroup,MapInterface<TKey,TValue>>
     */
    public function group(callable $callback): MapInterface;

    public function slice(int $length): MapInterface;

    /**
     * @param Closure(TValue,TKey):void $callback
     * @throws MappedErrorCollection If an error occured during execution.
     */
    public function forAll(callable $callback, bool $stopOnError = false): void;
}
