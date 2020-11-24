<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use Boesing\TypedArrays\Asset\ComparableObject;
use Boesing\TypedArrays\Asset\GenericObject;
use DateTimeImmutable;
use Generator;
use Lcobucci\Clock\FrozenClock;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webmozart\Assert\Assert;

use function array_map;
use function in_array;
use function strlen;
use function strnatcmp;
use function trim;

final class GenericMapTest extends TestCase
{
    /**
     * @psalm-param  array<string,mixed>       $initial
     * @psalm-param  array<string,mixed>       $expected
     * @psalm-param  list<array<string,mixed>> $stack
     * @dataProvider mergeStacks
     */
    public function testWillMerge(
        array $initial,
        array $expected,
        array $stack
    ): void {
        /** @var MapInterface<string,mixed> $map */
        $map = new GenericMap($initial);

        /** @psalm-var list<MapInterface<string,mixed>> $stackOfMaps */
        $stackOfMaps = array_map(
            static function (array $map): MapInterface {
                return new GenericMap($map);
            },
            $stack
        );

        Assert::allIsInstanceOf($stackOfMaps, MapInterface::class);

        $merged = $map->merge(
            ...$stackOfMaps
        );

        self::assertEquals($expected, $merged->toNativeArray());
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:array<string,mixed>,1:array<string,mixed>,2:list<array<string,mixed>>}>
     */
    public function mergeStacks(): Generator
    {
        yield 'single' => [
            ['foo' => 'bar'],
            [
                'foo' => 'bar',
                'baz' => 'bar',
            ],
            [
                ['baz' => 'bar'],
            ],
        ];

        yield 'multiple' => [
            ['foo' => 'bar'],
            [
                'foo' => 'bar',
                'baz' => 'bar',
                'qoo' => 'ooq',
            ],
            [
                ['baz' => 'bar'],
                ['baz' => 'bar', 'qoo' => 'ooq'],
            ],
        ];
    }

    /**
     * @psalm-param  array<string,mixed> $values
     * @psalm-param  (Closure(mixed,mixed):int)|null $callback
     * @psalm-param  array<string,mixed> $sorted
     * @dataProvider sorting
     */
    public function testSortUsesCallback(array $values, ?callable $callback, array $sorted): void
    {
        $list = new GenericMap($values);
        self::assertEquals(
            $list->sort($callback)
                ->toNativeArray(),
            $sorted
        );
    }

    /**
     * @psalm-return Generator<
     *     non-empty-string,
     *     array{0:array<string,mixed>,1:(Closure(mixed,mixed):int)|null,2:array<string,mixed>}
     * >
     */
    public function sorting(): Generator
    {
        yield 'descending' => [
            [
                'foo' => 'bar',
                'bar' => 'baz',
                'baz' => 'qoo',
            ],
            static function (string $a, string $b): int {
                return strnatcmp($b, $a);
            },
            [
                'baz' => 'qoo',
                'bar' => 'baz',
                'foo' => 'bar',
            ],
        ];

        yield 'ascending natural' => [
            [
                'baz' => 'qoo',
                'foo' => 'bar',
                'bar' => 'baz',
            ],
            null,
            [
                'foo' => 'bar',
                'bar' => 'baz',
                'baz' => 'qoo',
            ],
        ];
    }

    public function testKeysReturnExpectedValues(): void
    {
        $map  = new GenericMap(['foo' => 'bar', 'bar' => 'baz']);
        $keys = $map->keys();
        self::assertEquals(['foo', 'bar'], $keys->toNativeArray());
    }

    public function testCanRemoveElement(): void
    {
        $element  = new GenericObject(1);
        $element2 = new GenericObject(2);

        $map = new GenericMap(['first' => $element, 'second' => $element2]);

        $map = $map->removeElement($element);

        self::assertEquals(['second' => $element2], $map->toNativeArray());
    }

    public function testCanRemoveByKey(): void
    {
        $element  = new GenericObject(1);
        $element2 = new GenericObject(2);

        $map = new GenericMap(['first' => $element, 'second' => $element2]);

        $map = $map->unset('first');

        self::assertEquals(['second' => $element2], $map->toNativeArray());
    }

    public function testDiffKeys(): void
    {
        /** @psalm-var MapInterface<string,string> $map1 */
        $map1 = new GenericMap(['foo' => 'bar']);
        /** @psalm-var MapInterface<string,string> $map2 */
        $map2 = new GenericMap(['foo' => 'bar', 'bar' => 'baz']);

        self::assertEquals(['bar' => 'baz'], $map1->diffKeys($map2)->toNativeArray());
    }

    public function testWillMapValues(): void
    {
        $object1 = new GenericObject(1);
        $object2 = new GenericObject(2);
        /** @psalm-var MapInterface<string,GenericObject> $map */
        $map = new GenericMap([
            'first' => $object1,
            'second' => $object2,
        ]);

        $mapped = $map->map(static function (GenericObject $object): int {
            return $object->id;
        });

        self::assertEquals(['first' => 1, 'second' => 2], $mapped->toNativeArray());
    }

    /**
     * @psalm-param array<string,mixed> $initial
     * @psalm-param list<mixed>         $expected
     * @psalm-param (Closure(mixed,mixed):int)|null $sorter
     * @dataProvider orderedLists
     */
    public function testWillConvertToOrderedList(array $initial, array $expected, ?callable $sorter): void
    {
        $map  = new GenericMap($initial);
        $list = $map->toOrderedList($sorter);

        self::assertEquals($expected, $list->toNativeArray());
    }

    /**
     * @psalm-return Generator<
     *     non-empty-string,
     *     array{0:array<string,mixed>,1:list<mixed>,2:(Closure(mixed,mixed):int)|null}>
     */
    public function orderedLists(): Generator
    {
        yield 'integer' => [
            [
                'bar' => 2,
                'foo' => 1,
            ],
            [
                2,
                1,
            ],
            null,
        ];

        yield 'integer with sorting' => [
            [
                'bar' => 2,
                'foo' => 1,
            ],
            [
                1,
                2,
            ],
            static function (int $a, int $b): int {
                return $a <=> $b;
            },
        ];
    }

    public function testIntersectionReturnExpectedValues(): void
    {
        /** @var MapInterface<string,string> $map1 */
        $map1 = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'qoo' => 'ooq',
        ]);

        /** @var MapInterface<string,string> $map2 */
        $map2 = new GenericMap([
            'foo' => 'bar',
            'ooq' => 'qoo',
        ]);

        self::assertEquals(['foo' => 'bar'], $map1->intersect($map2)->toNativeArray());
    }

    public function testAssocIntersectionReturnExpectedValues(): void
    {
        /** @var MapInterface<string,string> $map1 */
        $map1 = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'qoo' => 'ooq',
        ]);

        /** @var MapInterface<string,string> $map2 */
        $map2 = new GenericMap([
            'foo' => 'bar',
            'ooq' => 'qoo',
        ]);

        self::assertEquals(['foo' => 'bar'], $map1->intersectAssoc($map2)->toNativeArray());
    }

    public function testAssocIntersectionReturnExpectedValuesWhenCustomComparatorWasProvided(): void
    {
        /** @var MapInterface<string,string> $map1 */
        $map1 = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'qoo' => 'ooq',
        ]);

        /** @var MapInterface<string,string> $map2 */
        $map2 = new GenericMap([
            'foo' => 'bar ',
            'ooq' => 'qoo',
        ]);

        self::assertEquals(['foo' => 'bar'], $map1->intersectAssoc($map2, static function (string $a, string $b): int {
            return trim($a) <=> trim($b);
        })->toNativeArray());
    }

    public function testIntersectionWithKeysReturnExpectedValues(): void
    {
        /** @var MapInterface<string,string> $map1 */
        $map1 = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'qoo' => 'ooq',
        ]);

        /** @var MapInterface<string,string> $map2 */
        $map2 = new GenericMap([
            'foo' => 'bar',
            'ooq' => 'qoo',
        ]);

        self::assertEquals(['foo' => 'bar'], $map1->intersectUsingKeys($map2)->toNativeArray());
    }

    public function testIntersectionWithKeysReturnExpectedValuesWhenCustomComparatorProvided(): void
    {
        /** @var MapInterface<string,string> $map1 */
        $map1 = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'qoo' => 'ooq',
        ]);

        /** @var MapInterface<string,string> $map2 */
        $map2 = new GenericMap([
            'foo' => 'bar',
            'ooq' => 'qoo',
        ]);

        self::assertEquals([
            'foo' => 'bar',
            'bar' => 'baz',
        ], $map1->intersectUsingKeys($map2, static function (string $a, string $b): int {
            return strlen($a) <=> strlen($b);
        })->toNativeArray());
    }

    /**
     * @param array<string,mixed> $initial
     * @param array<string,mixed> $other
     * @param array<string,mixed> $expected
     * @psalm-param (Closure(mixed,mixed):int)|null $comparator
     *
     * @dataProvider diffs
     */
    public function testCanDiff(array $initial, array $other, array $expected, ?callable $comparator): void
    {
        /** @var MapInterface<string,mixed> $map */
        $map = new GenericMap($initial);
        /** @var MapInterface<string,mixed> $otherMap */
        $otherMap = new GenericMap($other);
        $diff     = $map->diff($otherMap, $comparator);
        self::assertEquals($expected, $diff->toNativeArray());
    }

    /**
     * @psalm-return Generator<
     *     non-empty-string,
     *     array{
     *      0:array<string,mixed>,
     *      1:array<string,mixed>,
     *      2:array<string,mixed>,
     *      3:(Closure(mixed,mixed):int)|null
     *     }
     * >
     */
    public function diffs(): Generator
    {
        $clock = new FrozenClock(new DateTimeImmutable());

        yield 'simple' => [
            [
                'foo' => 'bar',
                'bar' => 'foo',
                'baz' => 'qoo',
            ],
            [
                'foo' => 'bar',
                'bar' => 'foo',
            ],
            ['baz' => 'qoo'],
            null,
        ];

        yield 'datetime' => [
            [
                'now' => $clock->now(),
                '10seconds' => $clock->now()->modify('+10 seconds'),
                '20seconds' => $clock->now()->modify('+20 seconds'),
            ],
            [
                'now' => $clock->now(),
                '10seconds' => $clock->now()->modify('+10 seconds'),
                '20seconds' => $clock->now()->modify('+20 seconds'),
                '30seconds' => $clock->now()->modify('+30 seconds'),
            ],
            [
                '30seconds' => $clock->now()->modify('+30 seconds'),
            ],
            null,
        ];

        $value1 = new ComparableObject(1);
        $value2 = new ComparableObject(2);

        yield 'comparator' => [
            [
                'value1' => $value1,
                'value2' => $value2,
            ],
            ['value1' => $value1],
            ['value2' => $value2],
            null,
        ];

        $object1 = new stdClass();
        $object2 = new stdClass();

        yield 'object' => [
            ['object1' => $object1],
            [
                'object1' => $object1,
                'object2' => $object2,
            ],
            ['object2' => $object2],
            null,
        ];

        $object1 = new GenericObject(1);
        $object2 = new GenericObject(2);

        yield 'custom' => [
            [
                'object1' => $object1,
                'object2' => $object2,
            ],
            ['object2' => $object2],
            ['object1' => $object1],
            static function (object $a, object $b): int {
                return $a->id <=> $b->id;
            },
        ];
    }

    public function testCanIntersectWithUserFunctions(): void
    {
        $keyComparator = static function (string $a, string $b): int {
            return $a <=> $b;
        };

        $valueComparator = static function (string $a, string $b): int {
            return $a <=> $b;
        };

        /** @var MapInterface<string,string> $map1 */
        $map1 = new GenericMap([
            'foo' => 'bar',
            'qoo' => 'ooq',
        ]);

        /** @var MapInterface<string,string> $map2 */
        $map2 = new GenericMap(['qoo' => 'ooq']);

        self::assertEquals(
            ['qoo' => 'ooq'],
            $map1->intersectUserAssoc($map2, $valueComparator, $keyComparator)
                ->toNativeArray()
        );
    }

    public function testGetThrowsOutOfBoundsExceptionWhenKeyDoesNotExist(): void
    {
        /** @var MapInterface<string,string> $map */
        $map = new GenericMap([]);
        $this->expectException(OutOfBoundsException::class);
        $map->get('foo');
    }

    public function testHasDetectsExistingKey(): void
    {
        /** @var MapInterface<string,string> $map */
        $map = new GenericMap(['foo' => 'bar']);

        self::assertTrue($map->has('foo'));
    }

    public function testHasReturnsFalseOnEmptyMap(): void
    {
        /** @var MapInterface<string,string> $map */
        $map = new GenericMap([]);
        self::assertFalse($map->has('foo'));
    }

    public function testHasReturnsFalseForDueToCaseSensitivity(): void
    {
        /** @var MapInterface<string,string> $map */
        $map = new GenericMap(['foo' => 'bar']);
        self::assertFalse($map->has('Foo'));
    }

    /**
     * @template    TValue
     * @psalm-param array<string,TValue> $initial
     * @psalm-param Closure(TValue $value):bool $callback
     * @psalm-param array<string,TValue> $filteredExpectation
     * @psalm-param array<string,TValue> $unfilteredExpectation
     *
     * @dataProvider partitions
     */
    public function testPartitioningReturnsTwoMapsWithExpectedValues(
        array $initial,
        callable $callback,
        array $filteredExpectation,
        array $unfilteredExpectation
    ): void {
        $map = new GenericMap($initial);

        [$filtered, $unfiltered] = $map->partition($callback);
        self::assertEquals($filtered->toNativeArray(), $filteredExpectation);
        self::assertEquals($unfiltered->toNativeArray(), $unfilteredExpectation);
    }

    /**
     * @return Generator<
     *     string,
     *     array{0:array<string,mixed>,1:Closure(mixed $value):bool,2:array<string,mixed>,3:array<string,mixed>}
     * >
     */
    public function partitions(): Generator
    {
        yield 'all filtered' => [
            [
                'foo' => 'bar',
                'bar' => 'baz',
                'qoo' => 'ooq',
            ],
            static function (): bool {
                return true;
            },
            [
                'foo' => 'bar',
                'bar' => 'baz',
                'qoo' => 'ooq',
            ],
            [],
        ];

        yield 'none filtered' => [
            [
                'foo' => 'bar',
                'bar' => 'baz',
                'qoo' => 'ooq',
            ],
            static function (): bool {
                return false;
            },
            [],
            [
                'foo' => 'bar',
                'bar' => 'baz',
                'qoo' => 'ooq',
            ],
        ];

        yield 'some filtered' => [
            [
                'foo' => 'bar',
                'bar' => 'baz',
                'qoo' => 'ooq',
            ],
            static function (string $value): bool {
                return in_array($value, ['baz', 'ooq'], true);
            },
            [
                'bar' => 'baz',
                'qoo' => 'ooq',
            ],
            ['foo' => 'bar'],
        ];
    }

    public function testWillFilter(): void
    {
        $map = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
        ]);

        $filtered = $map->filter(
            static function (string $value): bool {
                return $value === 'bar';
            }
        );

        self::assertNotSame($map, $filtered);
        self::assertCount(1, $filtered);
        self::assertTrue($filtered->has('foo'));
        self::assertEquals('bar', $filtered->get('foo'));
    }

    public function testWillGroupValuesToNewInstancesOfInitialInstance(): void
    {
        $map = new GenericMap([
            'foo' => $object1 = new GenericObject(1),
            'bar' => $object2 = new GenericObject(2),
        ]);

        $grouped = $map->group(static function (GenericObject $object): string {
            return $object->id % 2 ? 'a' : 'b';
        });

        self::assertTrue($grouped->has('a'));
        self::assertTrue($grouped->has('b'));

        $a = $grouped->get('a');
        self::assertCount(1, $a);
        self::assertEquals($object1, $a->get('foo'));
        $b = $grouped->get('b');
        self::assertCount(1, $b);
        self::assertEquals($object2, $b->get('bar'));
    }

    /**
     * @template T
     * @psalm-param array<string,T> $elements
     * @psalm-param Closure(T):bool $callback
     * @dataProvider satisfactions
     */
    public function testAllElementsWillSatisfyCallback(array $elements, callable $callback): void
    {
        $map = new GenericMap($elements);
        self::assertTrue($map->allSatisfy($callback));
    }

    public function testElementsWontSatisfyCallback(): void
    {
        $map = new GenericMap(['foo' => 'bar']);

        self::assertFalse($map->allSatisfy(static function (): bool {
            return false;
        }));
    }

    public function testEmptyMapWillSatisfyCallback(): void
    {
        $map = new GenericMap([]);
        self::assertTrue($map->allSatisfy(static function (): bool {
            return false;
        }));
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:array<non-empty-string,mixed>,1:Closure(mixed):bool}>
     */
    public function satisfactions(): Generator
    {
        yield 'only 1' => [
            [
                'one' => 1,
                'another one' => 1,
            ],
            static function (int $value): bool {
                return $value === 1;
            },
        ];

        yield 'all same string length' => [
            [
                'foo' => 'foo',
                'bar' => 'foo',
            ],
            static function (string $value): bool {
                return strlen($value) === 3;
            },
        ];
    }

    /**
     * @template T
     * @psalm-param array<non-empty-string,T> $data
     * @psalm-param Closure(T):bool $callback
     * @dataProvider existenceTests
     */
    public function testWillFindExistenceOfEntry(array $data, callable $callback, bool $exists): void
    {
        $map = new GenericMap($data);

        self::assertSame($exists, $map->exists($callback));
    }

    public function testEmptyMapWontFindExistence(): void
    {
        $map = new GenericMap();
        self::assertFalse($map->exists(static function (): bool {
            return true;
        }));
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:array<non-empty-string,mixed>,1:Closure(mixed):bool,2:bool}>
     */
    public function existenceTests(): Generator
    {
        yield 'simple' => [
            [
                'one' => 1,
                'two' => 2,
                'another one' => 1,
            ],
            static function (int $value): bool {
                return $value === 2;
            },
            true,
        ];

        yield 'loose comparison' => [
            [
                'one' => 1,
                'two' => 2,
                'three' => 3,
            ],
            static function (int $value): bool {
                // @codingStandardsIgnoreStart
                return $value == '2';
                // @codingStandardsIgnoreEnd
            },
            true,
        ];

        yield 'not found' => [
            [
                'foo' => 'foo',
                'bar' => 'bar',
                'baz' => 'baz',
            ],
            static function (string $value): bool {
                return $value === 'qoo';
            },
            false,
        ];
    }

    public function testWillSliceMap(): void
    {
        $map = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
        ]);

        $sliced = $map->slice(1);
        self::assertNotSame($sliced, $map);
        self::assertTrue($sliced->has('foo'));
        self::assertFalse($sliced->has('bar'));
    }

    public function testCanSliceTheEndOfAMap(): void
    {
        $map = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'baz' => 'qoo',
        ]);

        $sliced = $map->slice(-1);
        self::assertNotSame($sliced, $map);
        self::assertTrue($sliced->has('foo'));
        self::assertTrue($sliced->has('bar'));
        self::assertFalse($sliced->has('baz'));
    }
}
