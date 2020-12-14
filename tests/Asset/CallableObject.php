<?php

declare(strict_types=1);

namespace Boesing\TypedArrays\Asset;

use LogicException;
use PHPUnit\Framework\Assert;

use function func_get_args;

final class CallableObject
{
    /** @var array<int,array<int,mixed>> */
    private $argumentAssertions;

    /** @var int */
    private $called = 0;

    /**
     * @param list<list<mixed>> $argumentAssertions
     */
    public function __construct(array ...$argumentAssertions)
    {
        $this->argumentAssertions = $argumentAssertions;
    }

    public function __invoke(): void
    {
        $argument = func_get_args();
        $expected = $this->argumentAssertions[$this->called] ?? null;
        if ($expected === null) {
            throw new LogicException('Cannot make assertion on on undefined call');
        }

        $this->called++;
        Assert::assertEquals($expected, $argument);
    }
}
