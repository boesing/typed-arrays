<?php
declare(strict_types=1);

namespace Boesing\TypedArrays;

use function array_diff_ukey;
use function array_intersect_ukey;
use function array_keys;
use function array_uintersect;
use function array_uintersect_uassoc;
use function array_values;

/**
 * @template            TKey of array-key
 * @template            TValue
 * @template-extends    Array_<TKey,TValue>
 * @template-implements MapInterface<TKey,TValue>
 */
abstract class Map extends Array_ implements MapInterface
{

    public function merge(...$stack): MapInterface
    {
        $instance = clone $this;
        foreach ($stack as $list) {
            $instance->data = array_merge($instance->data, $list->toNativeArray());
        }

        return $instance;
    }

    public function sort(?callable $callback = null): MapInterface
    {
        $data = $this->data;
        $instance = clone $this;
        if ($callback === null) {
            asort($data, SORT_NATURAL);
            $instance->data = $data;

            return $instance;
        }

        uasort($data, $callback);
        $instance->data = $data;

        return $instance;
    }

    public function diffKeys(MapInterface $other, ?callable $keyComparator = null): MapInterface
    {
        $instance = clone $this;
        $otherData = $other->toNativeArray();

        /** @psalm-var array<TKey,TValue> $diff1 */
        $diff1 = array_diff_ukey($this->data, $otherData, $keyComparator ?? $this->keyComparator());
        /** @psalm-var array<TKey,TValue> $diff2 */
        $diff2 = array_diff_ukey($otherData, $this->data, $keyComparator ?? $this->keyComparator());
        $merged = array_merge(
            $diff1,
            $diff2
        );

        $instance->data = $merged;

        return $instance;
    }

    /**
     * @return callable(TKey $a,TKey $b):int
     */
    private function keyComparator(): callable
    {
        return static function ($a, $b): int {
            return $a <=> $b;
        };
    }

    public function toOrderedList(?callable $sorter = null): OrderedListInterface
    {
        if ($sorter === null) {
            return new GenericOrderedList(array_values($this->data));
        }

        $data = $this->data;
        usort($data, $sorter);

        return new GenericOrderedList($data);
    }

    public function filter(callable $callback): MapInterface
    {
        $instance = clone $this;
        $instance->data = array_filter($this->data, $callback, ARRAY_FILTER_USE_BOTH);

        return $instance;
    }

    public function keys(): OrderedListInterface
    {
        $keys = array_keys($this->data);

        return new GenericOrderedList($keys);
    }

    public function put($key, $value): MapInterface
    {
        $instance = clone $this;
        $instance->data[$key] = $value;

        return $instance;
    }

    public function get($key)
    {
        return $this->data[$key] ?? null;
    }

    public function intersect(MapInterface $other, ?callable $valueComparator = null): MapInterface
    {
        $instance = clone $this;
        $instance->data = $this->intersection($other, $valueComparator, null);

        return $instance;
    }

    /**
     * @psalm-param MapInterface<TKey,TValue> $other
     * @psalm-param  (Closure(TValue $a,TValue $b):int)|null $valueComparator
     * @psalm-param  (Closure(TKey $value,TKey $b):int)|null $keyComparator
     * @psalm-return array<TKey,TValue>
     */
    private function intersection(MapInterface $other, ?callable $valueComparator, ?callable $keyComparator): array
    {
        if ($valueComparator && $keyComparator) {
            /** @psalm-var array<TKey,TValue> $intersection */
            $intersection = array_uintersect_uassoc($this->data, $other->toNativeArray(), $valueComparator,
                $keyComparator);

            return $intersection;
        }

        if ($keyComparator) {
            /** @psalm-var array<TKey,TValue> $intersection */
            $intersection = array_intersect_ukey($this->data, $other->toNativeArray(), $keyComparator);

            return $intersection;
        }

        if (!$valueComparator) {
            $valueComparator = $this->valueComparator();
        }

        /** @psalm-var array<TKey,TValue> $intersection */
        $intersection = array_uintersect($this->data, $other->toNativeArray(), $valueComparator);

        return $intersection;
    }

    public function intersectAssoc(MapInterface $other, ?callable $valueComparator = null): MapInterface
    {
        $instance = clone $this;
        $instance->data = $this->intersection($other, $valueComparator, null);

        return $instance;
    }

    public function intersectUsingKeys(MapInterface $other, ?callable $keyComparator = null): MapInterface
    {
        $instance = clone $this;
        $instance->data = $this->intersection($other, null, $keyComparator);

        return $instance;
    }

    public function intersectUserAssoc(
        MapInterface $other,
        ?callable $valueComparator = null,
        ?callable $keyComparator = null
    ): MapInterface {
        $instance = clone $this;
        $instance->data = $this->intersection($other, $valueComparator, $keyComparator);

        return $instance;
    }

    public function diff(MapInterface $other, ?callable $valueComparator = null): MapInterface
    {
        /** @psalm-var array<TKey,TValue> $diff1 */
        $diff1 = array_udiff(
            $this->toNativeArray(),
            $other->toNativeArray(),
            $valueComparator ?? $this->valueComparator()
        );

        /** @psalm-var array<TKey,TValue> $diff2 */
        $diff2 = array_udiff(
            $other->toNativeArray(),
            $this->toNativeArray(),
            $valueComparator ?? $this->valueComparator()
        );

        $instance = clone $this;
        $merged = array_merge(
            $diff1,
            $diff2
        );

        $instance->data = $merged;

        return $instance;
    }

    public function removeByKey($key): MapInterface
    {
        $instance = clone $this;
        unset($instance->data[$key]);

        return $instance;
    }
}
