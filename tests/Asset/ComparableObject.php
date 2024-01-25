<?php

declare(strict_types=1);

namespace Boesing\TypedArrays\Asset;

use Boesing\TypedArrays\ComparatorInterface;

/**
 * @psalm-immutable
 */
final class ComparableObject implements ComparatorInterface
{
    public function __construct(private int $id)
    {
    }

    public function compareWith($other): int
    {
        return $this->id <=> $other->id;
    }
}
