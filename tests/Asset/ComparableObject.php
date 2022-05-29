<?php

declare(strict_types=1);

namespace Boesing\TypedArrays\Asset;

use Boesing\TypedArrays\ComparatorInterface;

/**
 * @psalm-immutable
 */
final class ComparableObject implements ComparatorInterface
{
    private int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function compareWith($other): int
    {
        return $this->id <=> $other->id;
    }
}
