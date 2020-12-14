<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

interface OrderedListFactoryInterface
{
    /**
     * @param list<TValue> $values
     *
     * @return OrderedListInterface<TValue>
     *
     * @template TValue
     */
    public function createOrderedList(array $values): OrderedListInterface;
}
