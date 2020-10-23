<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use Closure;
use OutOfBoundsException;
use Webmozart\Assert\Assert;

use function array_combine;
use function array_fill;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_replace;
use function array_slice;
use function array_udiff;
use function array_uintersect;
use function array_values;
use function hash;
use function is_callable;
use function serialize;
use function sort;
use function sprintf;
use function usort;

use const SORT_NATURAL;

/**
 * @template            TValue
 * @template-extends    Array_<int,TValue>
 * @template-implements OrderedListInterface<TValue>
 */
abstract class OrderedList extends Array_ implements OrderedListInterface
{
    /**
     * @psalm-param list<TValue> $data
     */
    final public function __construct(array $data)
    {
        /** @psalm-suppress RedundantCondition */
        Assert::isList($data);
        parent::__construct($data);
    }

    public function merge(...$stack): OrderedListInterface
    {
        $instance = clone $this;
        $values   = array_map(static function (OrderedListInterface $list): array {
            return $list->toNativeArray();
        }, $stack);

        $instance->data = array_values(array_merge($this->data, ...$values));

        return $instance;
    }

    public function map(callable $callback): OrderedListInterface
    {
        return new GenericOrderedList(array_values(
            array_map($callback, $this->data)
        ));
    }

    public function add($element): OrderedListInterface
    {
        $instance         = clone $this;
        $instance->data[] = $element;

        return $instance;
    }

    /**
     * @psalm-mutation-free
     */
    public function at(int $position)
    {
        if (! array_key_exists($position, $this->data)) {
            throw new OutOfBoundsException(sprintf('There is no value stored in that position: %d', $position));
        }

        return $this->data[$position];
    }

    public function sort(?callable $callback = null): OrderedListInterface
    {
        $data     = $this->data;
        $instance = clone $this;
        if ($callback === null) {
            sort($data, SORT_NATURAL);
            $instance->data = $data;

            return $instance;
        }

        usort($data, $callback);
        $instance->data = $data;

        return $instance;
    }

    public function diff(OrderedListInterface $other, ?callable $valueComparator = null): OrderedListInterface
    {
        $diff1 = array_udiff(
            $this->toNativeArray(),
            $other->toNativeArray(),
            $valueComparator ?? $this->valueComparator()
        );
        $diff2 = array_udiff(
            $other->toNativeArray(),
            $this->toNativeArray(),
            $valueComparator ?? $this->valueComparator()
        );

        $instance       = clone $this;
        $instance->data = array_values(array_merge(
            $diff1,
            $diff2
        ));

        return $instance;
    }

    public function intersect(OrderedListInterface $other, ?callable $valueComparator = null): OrderedListInterface
    {
        $instance       = clone $this;
        $instance->data = array_values(array_uintersect(
            $instance->data,
            $other->toNativeArray(),
            $valueComparator ?? $this->valueComparator()
        ));

        return $instance;
    }

    public function toMap(callable $keyGenerator): MapInterface
    {
        $keys = array_map($keyGenerator, $this->data);
        Assert::allStringNotEmpty($keys);

        $combined = array_combine(
            $keys,
            $this->data
        );

        /**
         * Integerish strings are converted to integer when used as array keys
         *
         * @link https://3v4l.org/Y2ld5
         */
        Assert::allStringNotEmpty(array_keys($combined));

        return new GenericMap($combined);
    }

    public function removeElement($element): OrderedListInterface
    {
        /** @psalm-suppress MissingClosureParamType */
        return $this->filter(
            static function ($value) use ($element): bool {
                return $value !== $element;
            }
        );
    }

    public function filter(callable $callback): OrderedListInterface
    {
        $instance       = clone $this;
        $instance->data = array_values(
            array_filter($this->data, $callback)
        );

        return $instance;
    }

    public function unify(
        ?callable $unificationIdentifierGenerator = null,
        ?callable $callback = null
    ): OrderedListInterface {
        /** @psalm-suppress MissingClosureParamType */
        $unificationIdentifierGenerator = $unificationIdentifierGenerator
            ?? static function ($value): string {
                return hash('sha256', serialize($value));
            };

        $instance = clone $this;

        /** @psalm-var MapInterface<TValue> $unified */
        $unified = new GenericMap([]);

        foreach ($instance->data as $value) {
            $identifier = $unificationIdentifierGenerator($value);
            try {
                $unique = $unified->get($identifier);
            } catch (OutOfBoundsException $exception) {
                $unique = $value;
            }

            if ($callback) {
                $unique = $callback($unique, $value);
            }

            $unified = $unified->put($identifier, $unique);
        }

        $instance->data = $unified->toOrderedList()->toNativeArray();

        return $instance;
    }

    public function fill(int $startIndex, int $amount, $value): OrderedListInterface
    {
        Assert::greaterThanEq($startIndex, 0, 'Given $startIndex must be greater than or equal to %2$s. Got: %s');
        Assert::greaterThanEq($amount, 1, 'Given $amount must be greater than or equal to %2$s. Got: %s');
        Assert::lessThanEq(
            $startIndex,
            $this->count(),
            'Give $startIndex must be less than or equal to %2$s to keep the list a continious list. Got: %s.'
        );

        $instance = clone $this;

        /** @psalm-var list<TValue> $combined */
        $combined = array_replace(
            $this->data,
            $this->createListFilledWithValues($startIndex, $amount, $value)
        );

        $instance->data = $combined;

        return $instance;
    }

    /**
     * @psalm-param TValue|Closure(int $index):TValue $value
     * @psalm-return array<int,TValue>
     */
    private function createListFilledWithValues(int $start, int $amount, $value): array
    {
        if (! is_callable($value)) {
            /** @psalm-var array<int,TValue> $list */
            $list = array_fill($start, $amount, $value);

            return $list;
        }

        $list = [];
        for ($index = $start; $index <= $amount; $index++) {
            $list[$index] = $value($index);
        }

        return $list;
    }

    /**
     * @psalm-mutation-free
     */
    public function slice(int $offset, ?int $length = null): OrderedListInterface
    {
        $instance       = clone $this;
        $instance->data = array_slice($this->data, $offset, $length, false);

        return $instance;
    }

    /**
     * @psalm-mutation-free
     */
    public function find(callable $callback)
    {
        foreach ($this->data as $value) {
            if ($callback($value)) {
                return $value;
            }
        }

        throw new OutOfBoundsException('Could not find value with provided callback.');
    }

    public function partition(callable $callback): array
    {
        $filtered = $unfiltered = [];

        foreach ($this->data as $element) {
            if ($callback($element)) {
                $filtered[] = $element;
                continue;
            }

            $unfiltered[] = $element;
        }

        $instance1       = clone $this;
        $instance1->data = $filtered;
        $instance2       = clone $this;
        $instance2->data = $unfiltered;

        return [$instance1, $instance2];
    }
}
