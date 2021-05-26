<?php

declare(strict_types=1);

namespace Boesing\TypedArrays\Asset;

/**
 * @psalm-mutation-free
 */
final class GenericObject
{
    /** @var int */
    public $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }
}
