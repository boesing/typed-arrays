<?php
declare(strict_types=1);

namespace Boesing\TypedArrays;

/**
 * @template         TValue
 * @template-extends ArrayInterface<string,TValue>
 */
interface MapInterface extends ArrayInterface
{

    /**
     * @psalm-param  Closure(TValue $value,string $key):bool $callback
     * @psalm-return MapInterface<TValue>
     * @psalm-immutable
     */
    public function filter(callable $callback): MapInterface;

    /**
     * @psalm-param  (Closure(TValue $a,TValue $b):int)|null $callback
     * @psalm-return MapInterface<TValue>
     * @psalm-immutable
     */
    public function sort(?callable $callback = null): MapInterface;

    /**
     * @param MapInterface[]                         $stack
     *
     * @psalm-param  list<MapInterface<TValue>> $stack
     *
     * @psalm-return MapInterface<TValue>
     * @psalm-immutable
     */
    public function merge(...$stack): MapInterface;

    /**
     * @psalm-param  MapInterface<TValue> $other
     * @psalm-param  (Closure(string $a,string $b):int)|null $keyComparator
     * @psalm-return MapInterface<TValue>
     * @psalm-immutable
     */
    public function diffKeys(MapInterface $other, ?callable $keyComparator = null): MapInterface;

    /**
     * @template     TNewValue
     * @psalm-param  Closure(TValue $a):TNewValue $callback
     *
     * @psalm-return MapInterface<TNewValue>
     * @psalm-immutable
     */
    public function map(callable $callback): MapInterface;

    /**
     * @psalm-param  MapInterface<TValue> $other
     * @psalm-param  (Closure(TValue $a,TValue $b):int)|null $valueComparator
     * @psalm-return MapInterface<TValue>
     * @psalm-immutable
     */
    public function intersect(MapInterface $other, ?callable $valueComparator = null): MapInterface;

    /**
     * @psalm-param  MapInterface<TValue> $other
     * @psalm-param  (Closure(TValue $a,TValue $b):int)|null $valueComparator
     * @psalm-return MapInterface<TValue>
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
     * @psalm-return MapInterface<TValue>
     * @psalm-immutable
     */
    public function remove($element): MapInterface;

    /**
     * @psalm-param  string $key
     * @psalm-return MapInterface<TValue>
     * @psalm-immutable
     */
    public function removeByKey($key): MapInterface;

    /**
     * @psalm-return OrderedListInterface<string>
     * @psalm-immutable
     */
    public function keys(): OrderedListInterface;

    /**
     * @psalm-param string   $key
     * @psalm-param TValue $value
     * @psalm-return MapInterface<TValue>
     * @psalm-immutable
     */
    public function put($key, $value): MapInterface;

    /**
     * @psalm-param string $key
     * @psalm-return TValue|null
     * @psalm-immutable
     */
    public function get($key);

    /**
     * @psalm-param MapInterface<TValue> $other
     * @psalm-return MapInterface<TValue>
     * @psalm-param  (Closure(TValue $a,TValue $b):int)|null $valueComparator
     * @psalm-immutable
     */
    public function intersectAssoc(MapInterface $other, ?callable $valueComparator = null): MapInterface;

    /**
     * @psalm-param MapInterface<TValue> $other
     * @psalm-return MapInterface<TValue>
     * @psalm-param  (Closure(string $a,string $b):int)|null $keyComparator
     * @psalm-immutable
     */
    public function intersectUsingKeys(MapInterface $other, ?callable $keyComparator = null): MapInterface;

    /**
     * @psalm-param MapInterface<TValue> $other
     * @psalm-param  (Closure(TValue $a,TValue $b):int)|null $valueComparator
     * @psalm-param  (Closure(string $a,string $b):int)|null $keyComparator
     * @psalm-return MapInterface<TValue>
     * @psalm-immutable
     */
    public function intersectUserAssoc(
        MapInterface $other,
        ?callable $valueComparator = null,
        ?callable $keyComparator = null
    ): MapInterface;
}
