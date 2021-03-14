<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use PHPUnit\Framework\TestCase;
use Webmozart\Assert\Assert;

final class ForAllPromiseTest extends TestCase
{
    public function testWillNotExecuteTwice(): void
    {
        $executed = false;
        $task     = static function () use (&$executed): void {
            Assert::false($executed);
            $executed = true;
        };

        (new ForAllPromise($task))->execute();
        $this->expectNotToPerformAssertions();
    }
}
