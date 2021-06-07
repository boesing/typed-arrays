<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use OutOfBoundsException;
use RuntimeException;
use Throwable;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_replace;
use function array_reverse;
use function array_slice;
use function array_udiff;
use function array_uintersect;
use function array_values;
use function assert;
use function hash;
use function implode;
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
 * @psalm-immutable
 */
abstract class OrderedList extends Array_ implements OrderedListInterface
{
    /**
     * @psalm-param list<TValue> $data
     */
    final public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public function merge(...$stack): OrderedListInterface
    {
        $instance = clone $this;
        $values   = array_map(static function (OrderedListInterface $list): array {
            return $list->toNativeArray();
        }, $stack);

        $instance->data = array_values(array_merge($instance->data, ...$values));

        return $instance;
    }

    /**
     * @template     TNewValue
     * @psalm-param  callable(TValue,int):TNewValue $callback
     * @psalm-return OrderedListInterface<TNewValue>
     */
    public function map(callable $callback): OrderedListInterface
    {
        $data = [];
        foreach ($this->data as $index => $value) {
            $data[] = $callback($value, $index);
        }

        return new GenericOrderedList($data);
    }

    public function add($element): OrderedListInterface
    {
        $instance         = clone $this;
        $instance->data[] = $element;

        return $instance;
    }

    public function at(int $position)
    {
        if (! array_key_exists($position, $this->data)) {
            throw new OutOfBoundsException(sprintf('There is no value stored in that position: %d', $position));
        }

        return $this->data[$position];
    }

    public function sort(?callable $callback = null): OrderedListInterface
    {
        $instance = clone $this;
        $data     = $instance->data;
        if ($callback === null) {
            sort($data, SORT_NATURAL);
            $instance->data = $data;

            return $instance;
        }

        /** @psalm-suppress ImpureFunctionCall */
        usort($data, $callback);
        $instance->data = $data;

        return $instance;
    }

    public function diff(OrderedListInterface $other, ?callable $valueComparator = null): OrderedListInterface
    {
        $instance = clone $this;

        $valueComparator = $valueComparator ?? $this->valueComparator();

        /** @psalm-suppress ImpureFunctionCall */
        $diff1 = array_udiff(
            $instance->toNativeArray(),
            $other->toNativeArray(),
            $valueComparator
        );

        /** @psalm-suppress ImpureFunctionCall */
        $diff2 = array_udiff(
            $other->toNativeArray(),
            $instance->toNativeArray(),
            $valueComparator
        );

        $instance->data = array_values(array_merge(
            $diff1,
            $diff2
        ));

        return $instance;
    }

    public function intersect(OrderedListInterface $other, ?callable $valueComparator = null): OrderedListInterface
    {
        $instance = clone $this;
        /** @psalm-suppress ImpureFunctionCall */
        $instance->data = array_values(array_uintersect(
            $instance->data,
            $other->toNativeArray(),
            $valueComparator ?? $this->valueComparator()
        ));

        return $instance;
    }

    /**
     * @template TKey of non-empty-string
     * @psalm-param  callable(TValue,int):TKey $keyGenerator
     * @psalm-return MapInterface<TKey,TValue>
     */
    public function toMap(callable $keyGenerator): MapInterface
    {
        $instance = clone $this;
        $mapped   = [];
        foreach ($instance->data as $index => $value) {
            $key          = $keyGenerator($value, $index);
            $mapped[$key] = $value;
        }

        /**
         * Integerish strings are converted to integer when used as array keys
         *
         * @link https://3v4l.org/Y2ld5
         */
        Assert::allStringNotEmpty(array_keys($mapped));

        return new GenericMap($mapped);
    }

    public function removeElement($element): OrderedListInterface
    {
        return $this->filter(
            static function ($value) use ($element): bool {
                return $value !== $element;
            }
        );
    }

    public function filter(callable $callback): OrderedListInterface
    {
        $instance = clone $this;
        $filtered = [];
        foreach ($instance->data as $index => $value) {
            if (! $callback($value, $index)) {
                continue;
            }

            $filtered[] = $value;
        }

        $instance->data = $filtered;

        return $instance;
    }

    public function unify(
        ?callable $unificationIdentifierGenerator = null,
        ?callable $callback = null
    ): OrderedListInterface {
        /** @psalm-suppress MissingClosureParamType */
        $unificationIdentifierGenerator = $unificationIdentifierGenerator
            ?? static function ($value): string {
                $hash = hash('sha256', serialize($value));
                Assert::stringNotEmpty($hash);

                return $hash;
            };

        $instance = clone $this;

        /** @psalm-var MapInterface<non-empty-string,TValue> $unified */
        $unified = new GenericMap([]);

        foreach ($instance->data as $value) {
            $identifier = $unificationIdentifierGenerator($value);
            try {
                $unique = $unified->get($identifier);

                if ($callback) {
                    $unique = $callback($unique, $value);
                }
            } catch (OutOfBoundsException $exception) {
                $unique = $value;
            }

            $unified = $unified->put($identifier, $unique);
        }

        /** @psalm-suppress ImpureMethodCall */
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

        $combined = array_replace(
            $this->data,
            $this->createListFilledWithValues($startIndex, $amount, $value)
        );

        $instance->data = $combined;

        return $instance;
    }

    /**
     * @psalm-param TValue|callable(int):TValue $value
     * @psalm-return array<int,TValue>
     */
    private function createListFilledWithValues(int $start, int $amount, $value): array
    {
        $filled = [];
        for ($index = $start; $index < $amount; $index++) {
            /**
             * @psalm-suppress ImpureFunctionCall
             * @psalm-suppress MixedAssignment https://github.com/vimeo/psalm/issues/5384
             */
            $filled[$index] = is_callable($value) ? $value($index) : $value;
        }

        return $filled;
    }

    public function slice(int $offset, ?int $length = null): OrderedListInterface
    {
        $instance       = clone $this;
        $instance->data = array_slice($this->data, $offset, $length, false);

        return $instance;
    }

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

    /**
     * @template TGroup of non-empty-string
     * @psalm-param callable(TValue):TGroup $callback
     *
     * @psalm-return MapInterface<TGroup,OrderedListInterface<TValue>>
     */
    public function group(callable $callback): MapInterface
    {
        /** @var MapInterface<TGroup,OrderedListInterface<TValue>> $groups */
        $groups = new GenericMap([]);
        foreach ($this as $value) {
            $groupName = $callback($value);
            if (! $groups->has($groupName)) {
                $groups = $groups->put($groupName, new GenericOrderedList([$value]));
                continue;
            }

            $existingGroup = $groups->get($groupName);
            $existingGroup = $existingGroup->add($value);
            $groups        = $groups->put($groupName, $existingGroup);
        }

        return $groups;
    }

    /**
     * @psalm-return list<TValue>
     */
    public function toNativeArray(): array
    {
        $data = $this->data;
        Assert::isList($data);

        return $data;
    }

    /**
     * @psalm-return list<TValue>
     */
    public function jsonSerialize(): array
    {
        return $this->toNativeArray();
    }

    public function has(int $index): bool
    {
        return array_key_exists($index, $this->data);
    }

    public function forAll(callable $callback): ForAllPromiseInterface
    {
        /** @psalm-suppress InternalClass */
        return new class ($this->getIterator(), $callback) extends AbstractForAllPromise
        {
            protected function createThrowableErrorCollection(array $errors): OrderedErrorCollection
            {
                assert(array_values($errors) === $errors);

                return OrderedErrorCollection::create(new GenericOrderedList($errors));
            }
        };
    }

    public function reverse(): OrderedListInterface
    {
        /** @psalm-var list<TValue> $reversed */
        $reversed = array_reverse($this->data);

        return new GenericOrderedList($reversed);
    }

    public function join(string $separator = ''): string
    {
        try {
            return implode($separator, $this->data);
        } catch (Throwable $throwable) {
            throw new RuntimeException('Could not join ordered list.', 0, $throwable);
        }
    }
}
