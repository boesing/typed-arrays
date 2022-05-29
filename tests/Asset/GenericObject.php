<?php

declare(strict_types=1);

namespace Boesing\TypedArrays\Asset;

/**
 * @psalm-immutable
 */
final class GenericObject
{
    public int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }
}
