<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

interface ComparatorInterface
{
    /**
     * @psalm-pure
     * @psalm-param static $other
     */
    public function compareWith($other): int;
}
