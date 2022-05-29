<?php

declare(strict_types=1);

namespace Boesing\TypedArrays\Asset;

use LogicException;
use PHPUnit\Framework\Assert;

use function func_get_args;

/**
 * @psalm-immutable
 */
final class CallableObject
{
    /** @var list<list<mixed>> */
    private array $argumentAssertions;

    private int $called = 0;

    /**
     * @param list<list<mixed>> $argumentAssertions
     */
    public function __construct(array ...$argumentAssertions)
    {
        \Webmozart\Assert\Assert::isList($argumentAssertions);
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
        /** @psalm-suppress ImpureMethodCall We do want to verify this here */
        Assert::assertEquals($expected, $argument);
    }
}
