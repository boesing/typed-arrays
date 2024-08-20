<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use OutOfBoundsException;
use RuntimeException;
use Throwable;

use function array_diff_ukey;
use function array_intersect_ukey;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_slice;
use function array_udiff;
use function array_uintersect;
use function array_uintersect_uassoc;
use function array_values;
use function asort;
use function implode;
use function sprintf;
use function strcmp;
use function uasort;
use function uksort;
use function usort;

use const SORT_NATURAL;

/**
 * @template            TKey of string
 * @template            TValue
 * @template-extends    Array_<TKey,TValue>
 * @template-implements MapInterface<TKey,TValue>
 * @psalm-immutable
 */
abstract class Map extends Array_ implements MapInterface
{
    /**
     * @psalm-param array<TKey,TValue> $data
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public function merge(MapInterface ...$stack): MapInterface
    {
        $instance = clone $this;
        $merges   = [];
        foreach ($stack as $map) {
            $merges[] = $map->toNativeArray();
        }

        $instance->data = array_merge($instance->data, ...$merges);

        return $instance;
    }

    public function sort(callable|null $callback = null): MapInterface
    {
        $instance = clone $this;
        $data     = $instance->data;
        if ($callback === null) {
            asort($data, SORT_NATURAL);
            $instance->data = $data;

            return $instance;
        }

        /**
         * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
         *                                    value here.
         */
        uasort($data, $callback);
        $instance->data = $data;

        return $instance;
    }

    public function diffKeys(MapInterface $other, callable|null $keyComparator = null): MapInterface
    {
        $instance      = clone $this;
        $otherData     = $other->toNativeArray();
        $keyComparator = $keyComparator ?? $this->keyComparator();

        /**
         * @psalm-var array<TKey,TValue> $diff1
         * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
         *                                    value here.
         */
        $diff1 = array_diff_ukey($instance->data, $otherData, $keyComparator);
        /**
         * @psalm-var array<TKey,TValue> $diff2
         * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
         *                                    value here.
         */
        $diff2  = array_diff_ukey($otherData, $instance->data, $keyComparator);
        $merged = array_merge(
            $diff1,
            $diff2,
        );

        $instance->data = $merged;

        return $instance;
    }

    /**
     * @psalm-return callable(TKey,TKey):int
     */
    private function keyComparator(): callable
    {
        return function (string $a, string $b): int {
            return strcmp($a, $b);
        };
    }

    public function toOrderedList(callable|null $sorter = null): OrderedListInterface
    {
        if ($sorter === null) {
            return new GenericOrderedList(array_values($this->data));
        }

        $data = $this->data;

        /**
         * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
         *                                    value here.
         */
        usort($data, $sorter);

        return new GenericOrderedList($data);
    }

    public function filter(callable $callback): MapInterface
    {
        $instance = clone $this;
        $filtered = [];
        foreach ($instance->data as $key => $value) {
            /**
             * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
             *                                    value here.
             */
            if (! $callback($value, $key)) {
                continue;
            }

            $filtered[$key] = $value;
        }

        $instance->data = $filtered;

        return $instance;
    }

    public function keys(): OrderedListInterface
    {
        $keys = array_keys($this->data);

        return new GenericOrderedList($keys);
    }

    public function values(): OrderedListInterface
    {
        return $this->toOrderedList();
    }

    public function put($key, $value): MapInterface
    {
        $instance             = clone $this;
        $instance->data[$key] = $value;

        return $instance;
    }

    public function get(string $key)
    {
        if (! $this->has($key)) {
            throw new OutOfBoundsException(sprintf('There is no value stored for provided key: %s', $key));
        }

        return $this->data[$key];
    }

    public function intersect(MapInterface $other, callable|null $valueComparator = null): MapInterface
    {
        $instance       = clone $this;
        $instance->data = $instance->intersection($other, $valueComparator, null);

        return $instance;
    }

    /**
     * @psalm-param MapInterface<TKey,TValue> $other
     * @psalm-param (callable(TValue,TValue):int)|null $valueComparator
     * @psalm-param (callable(TKey,TKey):int)|null $keyComparator
     * @psalm-return array<TKey,TValue>
     * @phpcsSuppress SlevomatCodingStandard.Classes.UnusedPrivateElements.UnusedMethod
     */
    private function intersection(MapInterface $other, callable|null $valueComparator, callable|null $keyComparator): array
    {
        if ($valueComparator !== null && $keyComparator !== null) {
            /**
             * @psalm-var array<TKey,TValue> $intersection
             * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
             *                                    value here.
             */
            $intersection = array_uintersect_uassoc(
                $this->data,
                $other->toNativeArray(),
                $valueComparator,
                $keyComparator,
            );

            return $intersection;
        }

        if ($keyComparator !== null) {
            /**
             * @psalm-var array<TKey,TValue> $intersection
             * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
             *                                    value here.
             */
            $intersection = array_intersect_ukey($this->data, $other->toNativeArray(), $keyComparator);

            return $intersection;
        }

        if ($valueComparator === null) {
            $valueComparator = $this->valueComparator();
        }

        /**
         * @psalm-var array<TKey,TValue> $intersection
         * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
         *                                    value here.
         */
        $intersection = array_uintersect($this->data, $other->toNativeArray(), $valueComparator);

        return $intersection;
    }

    public function intersectAssoc(MapInterface $other, callable|null $valueComparator = null): MapInterface
    {
        $instance       = clone $this;
        $instance->data = $instance->intersection($other, $valueComparator, null);

        return $instance;
    }

    public function intersectUsingKeys(MapInterface $other, callable|null $keyComparator = null): MapInterface
    {
        $instance       = clone $this;
        $instance->data = $instance->intersection($other, null, $keyComparator);

        return $instance;
    }

    public function intersectUserAssoc(
        MapInterface $other,
        callable|null $valueComparator = null,
        callable|null $keyComparator = null,
    ): MapInterface {
        $instance       = clone $this;
        $instance->data = $instance->intersection($other, $valueComparator, $keyComparator);

        return $instance;
    }

    public function diff(MapInterface $other, callable|null $valueComparator = null): MapInterface
    {
        /**
         * @psalm-var array<TKey,TValue> $diff1
         * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
         *                                    value here.
         */
        $diff1 = array_udiff(
            $this->toNativeArray(),
            $other->toNativeArray(),
            $valueComparator ?? $this->valueComparator(),
        );

        /**
         * @psalm-var array<TKey,TValue> $diff2
         * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
         *                                    value here.
         */
        $diff2 = array_udiff(
            $other->toNativeArray(),
            $this->toNativeArray(),
            $valueComparator ?? $this->valueComparator(),
        );

        $instance = clone $this;
        $merged   = array_merge(
            $diff1,
            $diff2,
        );

        $instance->data = $merged;

        return $instance;
    }

    public function unset($key): MapInterface
    {
        $instance = clone $this;
        unset($instance->data[$key]);

        return $instance;
    }

    public function removeElement($element): MapInterface
    {
        $instance = clone $this;
        foreach ($instance->data as $key => $value) {
            if ($value !== $element) {
                continue;
            }

            unset($instance->data[$key]);
        }

        return $instance;
    }

    /**
     * @template     TNewValue
     * @psalm-param  callable(TValue,TKey):TNewValue $callback
     * @psalm-return MapInterface<TKey,TNewValue>
     */
    public function map(callable $callback): MapInterface
    {
        $data = [];
        foreach ($this->data as $key => $value) {
            /**
             * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
             *                                    value here.
             */
            $data[$key] = $callback($value, $key);
        }

        return new GenericMap($data);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function partition(callable $callback): array
    {
        $filtered = $unfiltered = [];

        foreach ($this->data as $key => $element) {
            /**
             * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
             *                                    value here.
             */
            if ($callback($element)) {
                $filtered[$key] = $element;
                continue;
            }

            $unfiltered[$key] = $element;
        }

        $instance1       = clone $this;
        $instance1->data = $filtered;
        $instance2       = clone $this;
        $instance2->data = $unfiltered;

        return [$instance1, $instance2];
    }

    /**
     * @template TGroup of non-empty-string
     * @psalm-param callable(TValue):TGroup $callback
     *
     * @psalm-return MapInterface<TGroup,MapInterface<TKey,TValue>>
     */
    public function group(callable $callback): MapInterface
    {
        /**
         * @psalm-var MapInterface<TGroup,MapInterface<TKey,TValue>> $groups
         */
        $groups = new GenericMap([]);

        foreach ($this->data as $key => $value) {
            /**
             * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
             *                                    value here.
             */
            $groupIdentifier = $callback($value);
            try {
                $group = $groups->get($groupIdentifier);
            } catch (OutOfBoundsException) {
                $group       = clone $this;
                $group->data = [];
            }

            $groups = $groups->put($groupIdentifier, $group->put($key, $value));
        }

        return $groups;
    }

    public function slice(int $length): MapInterface
    {
        $instance       = clone $this;
        $instance->data = array_slice($instance->data, 0, $length, true);

        return $instance;
    }

    public function jsonSerialize(): array|null
    {
        if ($this->data === []) {
            return null;
        }

        return $this->data;
    }

    public function forAll(callable $callback): ForAllPromiseInterface
    {
        return new MapForAllPromise($this->getIterator(), $callback);
    }

    public function sortByKey(callable|null $sorter = null): MapInterface
    {
        $sorter   = $sorter ?? $this->keyComparator();
        $data     = $this->data;
        $instance = clone $this;
        /**
         * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
         *                                    value here.
         */
        uksort($data, $sorter);
        $instance->data = $data;

        return $instance;
    }

    public function join(string $separator = ''): string
    {
        try {
            /** @psalm-suppress MixedArgumentTypeCoercion Ignore invalid arguments here as we are catching the `Throwable` anyways. */
            return implode($separator, $this->data);
        } catch (Throwable $throwable) {
            throw new RuntimeException('Could not join map.', 0, $throwable);
        }
    }

    /**
     * @template TNewKey of string
     * @param callable(TKey,TValue):TNewKey $keyGenerator
     *
     * @return MapInterface<TNewKey,TValue>
     * @throws RuntimeException if a new key is being generated more than once.
     */
    public function keyExchange(callable $keyGenerator): MapInterface
    {
        /** @var MapInterface<TNewKey,TValue> $exchanged */
        $exchanged = new GenericMap();

        foreach ($this->data as $key => $value) {
            /**
             * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
             *                                    value here.
             */
            $newKey = $keyGenerator($key, $value);
            if ($exchanged->has($newKey)) {
                throw new RuntimeException(sprintf(
                    'Provided key generator generates the same key "%s" multiple times.',
                    $newKey,
                ));
            }

            $exchanged = $exchanged->put($newKey, $value);
        }

        return $exchanged;
    }

    public function toNativeArray(): array
    {
        return $this->data;
    }
}
