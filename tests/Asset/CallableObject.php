<?php

declare(strict_types=1);

namespace Boesing\TypedArrays\Asset;

use LogicException;
use PHPUnit\Framework\Assert;

use function func_get_args;

/**
 * @psalm-allow-private-mutation
 */
final class CallableObject
{
    /** @var list<list<mixed>> */
    private $argumentAssertions;

    /** @var int */
    private $called = 0;

    /**
     * @param list<list<mixed>> $argumentAssertions
     */
    public function __construct(array ...$argumentAssertions)
    {
        \Webmozart\Assert\Assert::isList($argumentAssertions);
        $this->argumentAssertions = $argumentAssertions;
    }

    /**
     * @psalm-pure
     */
    public function __invoke(): void
    {
        $argument = func_get_args();
        /** @psalm-suppress ImpurePropertyFetch, ImpureVariable */
        $expected = $this->argumentAssertions[$this->called] ?? null;
        if ($expected === null) {
            throw new LogicException('Cannot make assertion on on undefined call');
        }

        /** @psalm-suppress ImpurePropertyFetch, ImpureVariable */
        $this->called++;
        /** @psalm-suppress ImpureMethodCall */
        Assert::assertEquals($expected, $argument);
    }
}
