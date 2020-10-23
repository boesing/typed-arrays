<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use ArrayIterator;
use DateTimeInterface;
use OutOfBoundsException;
use Traversable;

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
 */
abstract class Array_ implements ArrayInterface
{
    /** @psalm-var array<TKey,TValue> */
    protected $data;

    /**
     * @psalm-param array<TKey,TValue> $data
     */
    public function __construct(array $data)
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

    /**
     * @psalm-mutation-free
     */
    public function contains($element): bool
    {
        return in_array($element, $this->data, true);
    }

    /**
     * @psalm-mutation-free
     */
    public function first()
    {
        if ($this->isEmpty()) {
            throw new OutOfBoundsException('There are no values available.');
        }

        return reset($this->data);
    }

    /**
     * @psalm-mutation-free
     */
    public function last()
    {
        if ($this->isEmpty()) {
            throw new OutOfBoundsException('There are no values available.');
        }

        return end($this->data);
    }

    /**
     * @psalm-mutation-free
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @psalm-mutation-free
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @psalm-mutation-free
     */
    public function toNativeArray(): array
    {
        return $this->data;
    }

    /**
     * @psalm-return callable(TValue $a,TValue $b):int
     */
    protected function valueComparator(): callable
    {
        return static function ($a, $b): int {
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
}
