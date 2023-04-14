<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use Throwable;

use function array_filter;

/**
 * @internal
 * @template TValue
 * @template-extends AbstractForAllPromise<string,TValue>
 */
final class MapForAllPromise extends AbstractForAllPromise
{
    protected function createThrowableErrorCollection(array $errors): MappedErrorCollection
    {
        /**
         * Filter out all keys which do not have errors.
         *
         * @var array<string,Throwable> $filtered
         */
        $filtered = array_filter($errors);

        return MappedErrorCollection::create(new GenericMap($filtered));
    }
}
