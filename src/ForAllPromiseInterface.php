<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

/**
 * Promise interface to create post-execution stuff.
 * This interface is not meant to be stored in any variable.
 */
interface ForAllPromiseInterface
{
    /**
     * @param callable():void $callback
     */
    public function finally(callable $callback): self;

    public function suppressErrors(): self;

    public function execute(): void;
}
