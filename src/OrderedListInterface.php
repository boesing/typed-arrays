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
     * Adds an element to the end of the list.
     *
     * @psalm-param TValue $element
     * @psalm-return OrderedListInterface<TValue>
     */
    public function add($element): OrderedListInterface;

    /**
     * Returns the item stored at the given index.
     *
     * @psalm-return TValue
     * @throws OutOfBoundsException If position does not exist.
     */
    public function at(int $position);

    /**
     * Filters out all values not matched by the callback.
     * This method is the equivalent of `array_filter`.
     *
     * @psalm-param  callable(TValue,int):bool $callback
     * @psalm-return OrderedListInterface<TValue>
     */
    public function filter(callable $callback): OrderedListInterface;

    /**
     * Sorts the items by using either the given callback or the native `SORT_NATURAL` logic of PHP.
     * This method is the equivalent of `sort`/`usort`.
     *
     * @psalm-param  (callable(TValue,TValue):int)|null $callback
     * @psalm-return OrderedListInterface<TValue>
     */
    public function sort(callable|null $callback = null): OrderedListInterface;

    /**
     * Merges all lists together. All provided lists are being appended to the end of this list in the order
     * they are passed to this method.
     * This method is the equivalent of `array_merge`.
     *
     * @psalm-param  list<OrderedListInterface<TValue>> $stack
     * @psalm-return OrderedListInterface<TValue>
     */
    public function merge(OrderedListInterface ...$stack): OrderedListInterface;

    /**
     * Converts the items of this list to a new list of items with the return value of the provided callback.
     * This method is the equivalent of `array_map`.
     *
     * @template     TNewValue
     * @psalm-param  callable(TValue,0|positive-int):TNewValue $callback
     * @psalm-return OrderedListInterface<TNewValue>
     */
    public function map(callable $callback): OrderedListInterface;

    /**
     * Creates an intersection of this list and the provided list while using the provided value comparator.
     * In case no value comparator is passed, a default comparator will be used. The default comparator can consume
     * every kind of data type. In case an object value is passed, that object can implement the {@see ComparatorInterface}
     * to provide custom comparison functionality.
     * This method is the equivalent of `array_intersect`.
     *
     * @psalm-param  OrderedListInterface<TValue> $other
     * @psalm-param  (callable(TValue,TValue):int)|null $valueComparator
     * @psalm-return OrderedListInterface<TValue>
     */
    public function intersect(OrderedListInterface $other, callable|null $valueComparator = null): OrderedListInterface;

    /**
     * Creates a diff of this list and the provided list while using the provided value comparator.
     * In case no value comparator is passed, a default comparator will be used. The default comparator can consume
     * every kind of data type. In case an object value is passed, that object can implement the {@see ComparatorInterface}
     * to provide custom comparison functionality.
     * This method is the equivalent of `array_diff`.
     *
     * @psalm-param  OrderedListInterface<TValue> $other
     * @psalm-param  (callable(TValue,TValue):int)|null $valueComparator
     * @psalm-return OrderedListInterface<TValue>
     */
    public function diff(OrderedListInterface $other, callable|null $valueComparator = null): OrderedListInterface;

    /**
     * Creates a map of this ordered list by using the provided key generator to generate dedicated keys for each item.
     *
     * @template TKeyForMap of non-empty-string
     * @psalm-param  callable(TValue,0|positive-int):TKeyForMap $keyGenerator
     * @psalm-return MapInterface<TKeyForMap,TValue>
     */
    public function toMap(callable $keyGenerator): MapInterface;

    /**
     * Removes a specific element from the list. In case the element was stored multiple times, all occurrences are being
     * removed.
     *
     * @psalm-param  TValue $element
     * @psalm-return OrderedListInterface<TValue>
     */
    public function removeElement($element): OrderedListInterface;

    /**
     * Creates a unified list of items.
     * In case a unification identifier generator is passed, one can generate a non-empty-string (for example a unique hash)
     * of a value. If not, a simple hashing strategy is being applied to identify identical values from this list.
     * In case a callback is provided, all duplications are being passed to that callback so one keep track of these.
     *
     * @psalm-param (callable(TValue):non-empty-string)|null $unificationIdentifierGenerator
     * @psalm-param (callable(TValue,TValue):TValue)|null $callback This callback is called for duplications only.
     * @psalm-return OrderedListInterface<TValue>
     */
    public function unify(
        callable|null $unificationIdentifierGenerator = null,
        callable|null $callback = null,
    ): OrderedListInterface;

    /**
     * Fills the list with a value or values generated by the provided callback.
     * This can either fill an entire list or extend a list.
     * This method can not be used to change existing elements within the item storage.
     * This method is the equivalent to `array_fill` with some additions to it.
     *
     * @throws InvalidArgumentException if start index does is not fitting in the current list state.
     *
     * @psalm-param TValue|callable(int):TValue $value
     * @psalm-return OrderedListInterface<TValue>
     */
    public function fill(int $startIndex, int $amount, $value): OrderedListInterface;

    /**
     * Creates a slice the current list by using the provided arguments.
     * This method is the equivalent of `array_slice`.
     *
     * @psalm-return OrderedListInterface<TValue>
     */
    public function slice(int $offset, int|null $length = null): OrderedListInterface;

    /**
     * This method will limit the list to a maximum amount of items provided as length.
     * This method internally calls {@see OrderedListInterface::slice}.
     *
     * @param positive-int $length
     * @psalm-return OrderedListInterface<TValue>
     */
    public function limit(int $length): OrderedListInterface;

    /**
     * Uses the callback to detect the first match from within the items stored in this list while
     * returning that match.
     * In case there are multiple elements matching the callback, only the first item will be returned.
     *
     * @psalm-param callable(TValue):bool $callback
     * @psalm-return TValue
     * @throws OutOfBoundsException if value could not be found with provided callback.
     */
    public function find(callable $callback);

    /**
     * Partitions the current list into those items which are filtered by the callback and those which don't.
     *
     * @param callable(TValue $value):bool $callback
     *
     * @psalm-return array{0:OrderedListInterface<TValue>,1:OrderedListInterface<TValue>}
     */
    public function partition(callable $callback): array;

    /**
     * Groups the items by using the callback.
     *
     * @template TGroup of non-empty-string|non-empty-list<non-empty-string>
     * @psalm-param callable(TValue):TGroup $callback
     *
     * @psalm-return MapInterface<non-empty-string,OrderedListInterface<TValue>>
     */
    public function group(callable $callback): MapInterface;

    /**
     * Verifies that an item exists at the position of the index.
     * This method is the equivalent of `array_key_exists`.
     */
    public function has(int $index): bool;

    /**
     * Applies the callback to all items.
     * In case the callback will throw exceptions or errors, a list of those errors will be created.
     * After the method has been executed properly, an `OrderedErrorCollection` is being thrown so one can
     * see what index actually failed executing the callback.
     *
     * @param callable(TValue,int):void $callback
     * @throws OrderedErrorCollection If an error occured during execution.
     */
    public function forAll(callable $callback): ForAllPromiseInterface;

    /**
     * Returns a list where the items are being in reverse order of this list.
     * This method is the equivalent of `array_reverse`.
     *
     * @psalm-return OrderedListInterface<TValue>
     */
    public function reverse(): self;

    /**
     * Joins all the items together.
     * In case a `callback` is being passed, values which usually can't be just used within `implode` can be converted
     * to string.
     * This method is the equivalent of `implode`.
     *
     * @throws Error In case, the values are not `string` or {@see Stringable}.
     */
    public function join(string $separator = ''): string;

    /**
     * Will create a native array containing all the values of this list.
     *
     * @psalm-return list<TValue>
     */
    public function toNativeArray(): array;

    /**
     * Will create a json serializable representation of this list.
     *
     * @psalm-return list<TValue>
     */
    public function jsonSerialize(): array;

    /**
     * Iterates over all items while passing them to the provided filter. If the filter matches, the index is
     * being returned and the iteration stops.
     * If no item matches the filter, `null` is being returned.
     *
     * @param callable(TValue):bool $filter
     *
     * @return 0|positive-int|null
     */
    public function findFirstMatchingIndex(callable $filter): int|null;

    /**
     * Adds an item at the beginning of the list.
     *
     * @param TValue $value
     * @return OrderedListInterface<TValue>
     */
    public function prepend($value): self;

    /**
     * Removes an element at the given index.
     *
     * @param 0|positive-int $index
     * @return OrderedListInterface<TValue>
     */
    public function removeAt(int $index): self;

    /**
     * Shuffles all the values within the list.
     *
     * @return OrderedListInterface<TValue>
     */
    public function shuffle(): self;

    /**
     * Combines multiple lists into one.
     *
     * @param OrderedListInterface<TValue> $other
     * @param OrderedListInterface<TValue> ...$others
     * @return OrderedListInterface<TValue>
     */
    public function combine(OrderedListInterface $other, OrderedListInterface ...$others): self;
}
