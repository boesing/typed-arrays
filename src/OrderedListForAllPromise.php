<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use function array_values;
use function assert;

/**
 * @internal
 * @template TValue
 * @template-extends AbstractForAllPromise<int,TValue>
 */
final class OrderedListForAllPromise extends AbstractForAllPromise
{
    protected function createThrowableErrorCollection(array $errors): OrderedErrorCollection
    {
        assert(array_values($errors) === $errors);

        return OrderedErrorCollection::create(new GenericOrderedList($errors));
    }
}
