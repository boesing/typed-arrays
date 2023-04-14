<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use Boesing\TypedArrays\Asset\OrderedListForAllPromise;
use PHPUnit\Framework\TestCase;

final class ForAllPromiseTest extends TestCase
{
    public function testWillNotExecuteTwiceDueToDestructionOfObject(): void
    {
        $executed = false;
        $task     = function () use (&$executed): void {
            self::assertFalse($executed, 'Task was executed more than once!');
            $executed = true;
        };

        (new OrderedListForAllPromise(['foo'], $task))->execute();
        self::assertTrue($executed, 'Task was not executed!');
    }
}
