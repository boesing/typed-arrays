<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

interface MapFactoryInterface
{
    /**
     * @template TKey of string
     * @template TValue
     * @param array<TKey,TValue> $data
     * @return MapInterface<TKey,TValue>
     */
    public function createMap(array $data): MapInterface;
}
