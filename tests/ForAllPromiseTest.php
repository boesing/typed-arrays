<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use PHPUnit\Framework\TestCase;

use function array_values;
use function assert;

final class ForAllPromiseTest extends TestCase
{
    public function testWillNotExecuteTwiceDueToDestructionOfObject(): void
    {
        $executed = false;
        $task     = static function () use (&$executed): void {
            self::assertFalse($executed, 'Task was executed more than once!');
            $executed = true;
        };

        /** @psalm-suppress InternalClass */
        (new class (['foo'], $task) extends AbstractForAllPromise
        {
            protected function createThrowableErrorCollection(array $errors): OrderedErrorCollection
            {
                assert($errors === array_values($errors));

                return OrderedErrorCollection::create(new GenericOrderedList($errors));
            }
        })->execute();
        self::assertTrue($executed, 'Task was not executed!');
    }
}
