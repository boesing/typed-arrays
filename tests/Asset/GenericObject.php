<?php

declare(strict_types=1);

namespace Boesing\TypedArrays\Asset;

/**
 * @psalm-immutable
 */
final class GenericObject
{
    public function __construct(public int $id)
    {
    }
}
