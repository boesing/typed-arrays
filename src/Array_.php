<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use ArrayIterator;
use DateTimeInterface;
use OutOfBoundsException;
use Traversable;

use function array_reduce;
use function count;
use function end;
use function in_array;
use function is_object;
use function reset;
use function spl_object_id;

/**
 * @internal
 *
 * @template            TKey of array-key
 * @template            TValue
 * @template-implements ArrayInterface<TKey,TValue>
 * @psalm-immutable
 */
abstract class Array_ implements ArrayInterface
{
    /** @psalm-var array<TKey,TValue> */
    protected array $data;

    /**
     * @psalm-param array<TKey,TValue> $data
     */
    protected function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @psalm-return Traversable<TKey,TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    public function contains($element): bool
    {
        return in_array($element, $this->data, true);
    }

    public function first()
    {
        if ($this->isEmpty()) {
            throw new OutOfBoundsException('There are no values available.');
        }

        return reset($this->data);
    }

    public function last()
    {
        if ($this->isEmpty()) {
            throw new OutOfBoundsException('There are no values available.');
        }

        return end($this->data);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @psalm-return pure-callable(TValue $a,TValue $b):int
     */
    protected function valueComparator(): callable
    {
        return function ($a, $b): int {
            if (! is_object($a) || ! is_object($b)) {
                return $a <=> $b;
            }

            if ($a instanceof DateTimeInterface && $b instanceof DateTimeInterface) {
                return $a <=> $b;
            }

            if ($a instanceof ComparatorInterface && $b instanceof ComparatorInterface) {
                return $a->compareWith($b);
            }

            $a = spl_object_id($a);
            $b = spl_object_id($b);

            return $a <=> $b;
        };
    }

    public function allSatisfy(callable $callback): bool
    {
        foreach ($this->data as $value) {
            /**
             * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
             *                                    value here.
             */
            if (! $callback($value)) {
                return false;
            }
        }

        return true;
    }

    public function exists(callable $callback): bool
    {
        foreach ($this->data as $value) {
            /**
             * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
             *                                    value here.
             */
            if ($callback($value)) {
                return true;
            }
        }

        return false;
    }

    public function reduce(callable $callback, $initial)
    {
        $instance = clone $this;

        /**
         * @psalm-suppress ImpureFunctionCall Upstream projects have to ensure that they do not manipulate the
         *                                    value here.
         */
        return array_reduce($instance->data, $callback, $initial);
    }
}
