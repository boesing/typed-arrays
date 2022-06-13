<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use JsonSerializable;
use OutOfBoundsException;
use RuntimeException;

/**
 * @template         TKey of string
 * @template         TValue
 * @template-extends ArrayInterface<TKey,TValue>
 * @psalm-immutable
 */
interface MapInterface extends ArrayInterface, JsonSerializable
{
    /**
     * Filters out all values not matched by the callback.
     * This method is the equivalent of `array_filter`.
     *
     * @psalm-param  callable(TValue,TKey):bool $callback
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function filter(callable $callback): MapInterface;

    /**
     * Sorts the items by using either the given callback or the native `SORT_NATURAL` logic of PHP.
     * This method is the equivalent of `sort`/`usort`.
     *
     * @psalm-param  (callable(TValue,TValue):int)|null $callback
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function sort(?callable $callback = null): MapInterface;

    /**
     * Merges all maps together. Duplications are being overridden in the order they are being passed to this method.
     * This method is the equivalent of `array_merge`.
     *
     * @psalm-param  list<MapInterface<TKey,TValue>> $stack
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function merge(MapInterface ...$stack): MapInterface;

    /**
     * Creates a diff of the keys of this map and the keys of the provided map while using the provided value comparator.
     * In case no value comparator is passed, a default comparator will be used. The default comparator can consume
     * every kind of data type. In case an object value is passed, that object can implement the {@see ComparatorInterface}
     * to provide custom comparison functionality.
     * This method is the equivalent of `array_diff`.
     *
     * @psalm-param  MapInterface<TKey,TValue> $other
     * @psalm-param  (callable(TKey,TKey):int)|null $keyComparator
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function diffKeys(MapInterface $other, ?callable $keyComparator = null): MapInterface;

    /**
     * Converts the items of this map to a new map of items with the return value of the provided callback.
     * This method is the equivalent of `array_map`.
     *
     * @template     TNewValue
     * @psalm-param  callable(TValue,TKey):TNewValue $callback
     * @psalm-return MapInterface<TKey,TNewValue>
     */
    public function map(callable $callback): MapInterface;

    /**
     * Creates an intersection of this map and the provided map while using the provided value comparator.
     * In case no value comparator is passed, a default comparator will be used. The default comparator can consume
     * every kind of data type. In case an object value is passed, that object can implement the {@see ComparatorInterface}
     * to provide custom comparison functionality.
     * This method is the equivalent of `array_intersect`.
     *
     * @psalm-param  MapInterface<TKey,TValue> $other
     * @psalm-param  (callable(TValue,TValue):int)|null $valueComparator
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function intersect(MapInterface $other, ?callable $valueComparator = null): MapInterface;

    /**
     * Creates a diff of this map and the provided map while using the provided value comparator.
     * In case no value comparator is passed, a default comparator will be used. The default comparator can consume
     * every kind of data type. In case an object value is passed, that object can implement the {@see ComparatorInterface}
     * to provide custom comparison functionality.
     * This method is the equivalent of `array_diff`.
     *
     * @psalm-param  MapInterface<TKey,TValue> $other
     * @psalm-param  (callable(TValue,TValue):int)|null $valueComparator
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function diff(MapInterface $other, ?callable $valueComparator = null): MapInterface;

    /**
     * Creates an ordered list of the values contained in this map.
     * The items are being sorted by using the provided sorter. In case there is no sorter provided, the values
     * are just passed in the order they werestored in this map.
     *
     * @psalm-param (callable(TValue,TValue):int)|null $sorter
     * @psalm-return OrderedListInterface<TValue>
     */
    public function toOrderedList(?callable $sorter = null): OrderedListInterface;

    /**
     * Removes a specific element from the list. In case the element was stored multiple times, all occurrences are being
     * removed.
     *
     * @psalm-param  TValue $element
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function removeElement($element): MapInterface;

    /**
     * Removes a specific item within this map identified by the provided key.
     *
     * @psalm-param  TKey $key
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function unset($key): MapInterface;

    /**
     * Creates a list of the keys used to identify the items in this map.
     *
     * @psalm-return OrderedListInterface<TKey>
     */
    public function keys(): OrderedListInterface;

    /**
     * Creates a list of the values stored in this list.
     * This method is an alias of {@see MapInterface::toOrderedList}.
     *
     * @psalm-return OrderedListInterface<TValue>
     */
    public function values(): OrderedListInterface;

    /**
     * Adds or replaces a value for the provided key.
     *
     * @psalm-param TKey   $key
     * @psalm-param TValue $value
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-mutation-free
     */
    public function put($key, $value): MapInterface;

    /**
     * Returns the value for the provided key.
     *
     * @psalm-param TKey $key
     * @psalm-return TValue
     * @throws OutOfBoundsException if key does not exist.
     * @psalm-pure
     */
    public function get(string $key);

    /**
     * Creates an associative intersection of this map and the provided map using the provided value comparator.
     * In case no value comparator is passed, a default comparator will be used. The default comparator can consume
     * every kind of data type. In case an object value is passed, that object can implement the {@see ComparatorInterface}
     * to provide custom comparison functionality.
     * This method is the equivalent of `array_intersect_assoc`/`array_intersect_uassoc`.
     *
     * @psalm-param MapInterface<TKey,TValue> $other
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-param  (callable(TValue,TValue):int)|null $valueComparator
     */
    public function intersectAssoc(MapInterface $other, ?callable $valueComparator = null): MapInterface;

    /**
     * Creates an associative intersection of this map and the provided map using the provided key comparator.
     * In case no key comparator is passed, a default key comparator will be used.
     * This method is the equivalent of `array_intersect_key`/`array_intersect_ukey`.
     *
     * @psalm-param MapInterface<TKey,TValue> $other
     * @psalm-return MapInterface<TKey,TValue>
     * @psalm-param  (callable(TKey,TKey):int)|null $keyComparator
     */
    public function intersectUsingKeys(MapInterface $other, ?callable $keyComparator = null): MapInterface;

    /**
     * Creates an associative intersection of this map and the provided map using the provided value comparator.
     * In case no value comparator is passed, a default comparator will be used. The default comparator can consume
     * every kind of data type. In case an object value is passed, that object can implement the {@see ComparatorInterface}
     * to provide custom comparison functionality.
     * This method is the equivalent of `array_intersect_assoc`/`array_intersect_uassoc`/`array_intersect_key`/`array_intersect_ukey`.
     *
     * @psalm-param MapInterface<TKey,TValue> $other
     * @psalm-param  (callable(TValue,TValue):int)|null $valueComparator
     * @psalm-param  (callable(TKey,TKey):int)|null $keyComparator
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function intersectUserAssoc(
        MapInterface $other,
        ?callable $valueComparator = null,
        ?callable $keyComparator = null
    ): MapInterface;

    /**
     * Verifies that the map contains a value for the provided key.
     * This method is the equivalent of `array_key_exists`.
     *
     * @psalm-param TKey $key
     */
    public function has(string $key): bool;

    /**
     * Partitions the current map into those items which are filtered by the callback and those which don't.
     *
     * @psalm-param callable(TValue):bool $callback
     * @psalm-return array{0:MapInterface<TKey,TValue>,1:MapInterface<TKey,TValue>}
     */
    public function partition(callable $callback): array;

    /**
     * Groups the items of this object by using the callback.
     *
     * @template TGroup of non-empty-string
     * @psalm-param callable(TValue):TGroup $callback
     *
     * @psalm-return MapInterface<TGroup,MapInterface<TKey,TValue>>
     */
    public function group(callable $callback): MapInterface;

    /**
     * Creates a slice of this map. Due to the fact that this is a hashmap, an `offset` makes no sense.
     *
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function slice(int $length): MapInterface;

    /**
     * Applies the callback to all items.
     * In case the callback will throw exceptions or errors, a list of those errors will be created.
     * After the method has been executed properly, an `MappedErrorCollection` is being thrown so one can
     * see what item key actually failed executing the callback.
     *
     * @param callable(TValue,TKey):void $callback
     * @throws MappedErrorCollection If an error occured during execution.
     */
    public function forAll(callable $callback): ForAllPromiseInterface;

    /**
     * Sorts the map by sorting its keys.
     *
     * @param (callable(TKey,TKey):int)|null $sorter
     *
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function sortByKey(?callable $sorter = null): MapInterface;

    /**
     * Joins all the items together.
     * This method is the equivalent of `implode`.
     *
     * ror In case, the values are not `string` or {@see Stringable}.
     */
    public function join(string $separator = ''): string;

    /**
     * Creates a new map where the keys of items might have been exchanged with another key.
     *
     * @template TNewKey of string
     * @param callable(TKey,TValue):TNewKey $keyGenerator
     *
     * @return MapInterface<TNewKey,TValue>
     * @throws RuntimeException if a new key is being generated more than once.
     */
    public function keyExchange(callable $keyGenerator): MapInterface;

    /**
     * Will create a json serializable representation of this map.
     * Since an empty would be serialized as a list, `null` is being returned instead.
     *
     * @psalm-return non-empty-array<TKey,TValue>|null
     */
    public function jsonSerialize(): ?array;

    /**
     * Returns a native array equivalent of the {@see OrderedListInterface} or the {@see MapInterface}.
     *
     * @psalm-return array<TKey,TValue>
     */
    public function toNativeArray(): array;
}
