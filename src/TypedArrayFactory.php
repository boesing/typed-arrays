<?php
declare(strict_types=1);

namespace Boesing\TypedArrays;

final class TypedArrayFactory implements OrderedListFactoryInterface, MapFactoryInterface
{

    public function createMap(array $data): MapInterface
    {
        return new GenericMap($data);
    }

    public function createOrderedList(array $values): OrderedListInterface
    {
        return new GenericOrderedList($values);
    }
}
