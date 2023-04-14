<?php

declare(strict_types=1);

namespace Boesing\TypedArrays\Asset;

use Boesing\TypedArrays\AbstractForAllPromise;
use Boesing\TypedArrays\GenericOrderedList;
use Boesing\TypedArrays\OrderedErrorCollection;

use function array_values;
use function assert;

/**
 * @extends AbstractForAllPromise<int,string>
 */
final class OrderedListForAllPromise extends AbstractForAllPromise
{
    protected function createThrowableErrorCollection(array $errors): OrderedErrorCollection
    {
        assert($errors === array_values($errors));

        return OrderedErrorCollection::create(new GenericOrderedList($errors));
    }
}
