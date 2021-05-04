<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

/**
 * Promise interface to create post-execution stuff.
 * This interface is not meant to be stored in any variable.
 *
 * @template TKey of array-key
 * @template TValue
 */
interface ForAllPromiseInterface
{
    /**
     * @param callable():void $callback
     */
    public function finally(callable $callback): self;

    public function stopOnError(): self;

    public function suppressErrors(): self;

    /**
     * Will execute the provided callback for all entries.
     *
     * @throws OrderedErrorCollection|MappedErrorCollection If an error occurred during execution.
     */
    public function execute(): void;

    /**
     * Should trigger execute if it wasnt triggered before.
     *
     * @throws OrderedErrorCollection|MappedErrorCollection If an error occurred during execution.
     * @psalm-suppress MissingReturnType
     */
    public function __destruct();
}
