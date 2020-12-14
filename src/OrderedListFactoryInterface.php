<?php
declare(strict_types=1);

namespace Boesing\TypedArrays;

interface OrderedListFactoryInterface
{
    /**
     * @template TValue
     *
     * @param list<TValue> $values
     *
     * @return OrderedListInterface<TValue>
     */
    public function createOrderedList(array $values): OrderedListInterface;
}
