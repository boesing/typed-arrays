<?php
declare(strict_types=1);

namespace Boesing\TypedArrays;

/**
 * @template         TKey of array-key
 * @template         TValue
 * @template-extends ArrayInterface<TKey,TValue>
 */
interface MapInterface extends ArrayInterface
{

    /**
     * @psalm-param  Closure(TValue $value,TKey $key):bool $callback
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-immutable
     */
    public function filter(callable $callback): MapInterface;

    /**
     * @psalm-param  (Closure(TValue $a,TValue $b):int)|null $callback
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-immutable
     */
    public function sort(?callable $callback = null): MapInterface;

    /**
     * @param MapInterface[]                         $stack
     *
     * @psalm-param  list<MapInterface<TKey,TValue>> $stack
     *
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-immutable
     */
    public function merge(...$stack): MapInterface;

    /**
     * @psalm-param  MapInterface<TKey,TValue> $other
     * @psalm-param  (Closure(TKey $a,TKey $b):int)|null $keyComparator
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-immutable
     */
    public function diffKeys(MapInterface $other, ?callable $keyComparator = null): MapInterface;

    /**
     * @template     TNewValue
     * @psalm-param  Closure(TValue $a):TNewValue $callback
     *
     * @psalm-return MapInterface<TKey,TNewValue>
     * @psalm-immutable
     */
    public function map(callable $callback): MapInterface;

    /**
     * @psalm-param  MapInterface<TKey,TValue> $other
     * @psalm-param  (Closure(TValue $a,TValue $b):int)|null $valueComparator
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-immutable
     */
    public function intersect(MapInterface $other, ?callable $valueComparator = null): MapInterface;

    /**
     * @psalm-param  MapInterface<TKey,TValue> $other
     * @psalm-param  (Closure(TValue $a,TValue $b):int)|null $valueComparator
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-immutable
     */
    public function diff(MapInterface $other, ?callable $valueComparator = null): MapInterface;

    /**
     * @psalm-param (Closure(TValue $a,TValue $b):int)|null $sorter
     * @psalm-return OrderedListInterface<TValue>
     * @psalm-immutable
     */
    public function toOrderedList(?callable $sorter = null): OrderedListInterface;

    /**
     * @psalm-param  TValue $element
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-immutable
     */
    public function remove($element): MapInterface;

    /**
     * @psalm-param  TKey $key
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-immutable
     */
    public function removeByKey($key): MapInterface;

    /**
     * @psalm-return OrderedListInterface<TKey>
     * @psalm-immutable
     */
    public function keys(): OrderedListInterface;

    /**
     * @psalm-param TKey   $key
     * @psalm-param TValue $value
     * @psalm-return MapInterface<TKey, TValue>
     * @psalm-immutable
     */
    public function put($key, $value): MapInterface;

    /**
     * @psalm-param TKey $key
     * @psalm-return TValue|null
     * @psalm-immutable
     */
    public function get($key);

    /**
     * @psalm-param MapInterface<TKey,TValue> $other
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-param  (Closure(TValue $a,TValue $b):int)|null $valueComparator
     * @psalm-immutable
     */
    public function intersectAssoc(MapInterface $other, ?callable $valueComparator = null): MapInterface;

    /**
     * @psalm-param MapInterface<TKey,TValue> $other
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-param  (Closure(TKey $a,TKey $b):int)|null $keyComparator
     * @psalm-immutable
     */
    public function intersectUsingKeys(MapInterface $other, ?callable $keyComparator = null): MapInterface;

    /**
     * @psalm-param MapInterface<TKey,TValue> $other
     * @psalm-param  (Closure(TValue $a,TValue $b):int)|null $valueComparator
     * @psalm-param  (Closure(TKey $a,TKey $b):int)|null $keyComparator
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-immutable
     */
    public function intersectUserAssoc(
        MapInterface $other,
        ?callable $valueComparator = null,
        ?callable $keyComparator = null
    ): MapInterface;
}
