<?php
declare(strict_types=1);

namespace Boesing\TypedArrays;

use Boesing\TypedArrays\Asset\ComparableObject;
use Boesing\TypedArrays\Asset\GenericObject;
use DateTimeImmutable;
use Generator;
use Lcobucci\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;
use stdClass;

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
        $map = new GenericMap($initial);
        $merged = $map->merge(...
            array_map(
                static function (array $map): MapInterface {
                    return new GenericMap($map);
                },
                $stack
            )
        );

        self::assertEquals($expected, $merged->toNativeArray());
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:array<string,mixed>,1:array<string,mixed>,2:list<array<string,mixed>>}>
     */
    public function mergeStacks(): Generator
    {
        yield 'single' => [
            [
                'foo' => 'bar',
            ],
            [
                'foo' => 'bar',
                'baz' => 'bar',
            ],
            [
                ['baz' => 'bar',],
            ],
        ];

        yield 'multiple' => [
            [
                'foo' => 'bar',
            ],
            [
                'foo' => 'bar',
                'baz' => 'bar',
                'qoo' => 'ooq',
            ],
            [
                ['baz' => 'bar',],
                ['baz' => 'bar', 'qoo' => 'ooq',],
            ],
        ];
    }

    /**
     * @psalm-param  array<string,mixed> $values
     * @psalm-param  (Closure(mixed $a,mixed $b):int)|null $callback
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
     *     array{0:array<string,mixed>,1:(Closure(mixed $a,mixed $b):int)|null,2:array<string,mixed>}
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
        $map = new GenericMap(['foo' => 'bar', 'bar' => 'baz']);
        $keys = $map->keys();
        self::assertEquals(['foo', 'bar'], $keys->toNativeArray());
    }

    public function testCanRemoveElement(): void
    {
        $element = new GenericObject(1);
        $element2 = new GenericObject(2);

        $map = new GenericMap(['first' => $element, 'second' => $element2]);

        $map = $map->remove($element);

        self::assertEquals([
            'second' => $element2,
        ], $map->toNativeArray());
    }

    public function testCanRemoveByKey(): void
    {
        $element = new GenericObject(1);
        $element2 = new GenericObject(2);

        $map = new GenericMap(['first' => $element, 'second' => $element2]);

        $map = $map->removeByKey('first');

        self::assertEquals([
            'second' => $element2,
        ], $map->toNativeArray());
    }

    public function testDiffKeys(): void
    {
        /** @psalm-var MapInterface<string> $map1 */
        $map1 = new GenericMap(['foo' => 'bar']);
        /** @psalm-var MapInterface<string> $map2 */
        $map2 = new GenericMap(['foo' => 'bar', 'bar' => 'baz']);

        self::assertEquals(['bar' => 'baz'], $map1->diffKeys($map2)->toNativeArray());
    }

    public function testWillMapValues(): void
    {
        $object1 = new GenericObject(1);
        $object2 = new GenericObject(2);
        /** @psalm-var MapInterface<GenericObject> $map */
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
     * @psalm-param (Closure(mixed $a,mixed $b):int)|null $sorter
     * @dataProvider orderedLists
     */
    public function testWillConvertToOrderedList(array $initial, array $expected, ?callable $sorter): void
    {
        $map = new GenericMap($initial);
        $list = $map->toOrderedList($sorter);

        self::assertEquals($expected, $list->toNativeArray());
    }

    /**
     * @psalm-return Generator<
     *     non-empty-string,
     *     array{0:array<string,mixed>,1:list<mixed>,2:(Closure(mixed $a,mixed $b):int)|null}>
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
            }
        ];
    }

    public function testIntersectionReturnExpectedValues(): void
    {
        /** @var MapInterface<string> $map1 */
        $map1 = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'qoo' => 'ooq',
        ]);

        /** @var MapInterface<string> $map1 */
        $map2 = new GenericMap([
            'foo' => 'bar',
            'ooq' => 'qoo',
        ]);

        self::assertEquals([
            'foo' => 'bar',
        ], $map1->intersect($map2)->toNativeArray());
    }

    public function testAssocIntersectionReturnExpectedValues(): void
    {
        /** @var MapInterface<string> $map1 */
        $map1 = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'qoo' => 'ooq',
        ]);

        /** @var MapInterface<string> $map2 */
        $map2 = new GenericMap([
            'foo' => 'bar',
            'ooq' => 'qoo',
        ]);

        self::assertEquals([
            'foo' => 'bar',
        ], $map1->intersectAssoc($map2)->toNativeArray());
    }

    public function testAssocIntersectionReturnExpectedValuesWhenCustomComparatorWasProvided(): void
    {
        /** @var MapInterface<string> $map1 */
        $map1 = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'qoo' => 'ooq',
        ]);

        /** @var MapInterface<string> $map2 */
        $map2 = new GenericMap([
            'foo' => 'bar ',
            'ooq' => 'qoo',
        ]);

        self::assertEquals([
            'foo' => 'bar',
        ], $map1->intersectAssoc($map2, static function (string $a, string $b): int {
            return trim($a) <=> trim($b);
        })->toNativeArray());
    }

    public function testIntersectionWithKeysReturnExpectedValues(): void
    {
        /** @var MapInterface<string> $map1 */
        $map1 = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'qoo' => 'ooq',
        ]);

        /** @var MapInterface<string> $map2 */
        $map2 = new GenericMap([
            'foo' => 'bar',
            'ooq' => 'qoo',
        ]);

        self::assertEquals([
            'foo' => 'bar',
        ], $map1->intersectUsingKeys($map2)->toNativeArray());
    }

    public function testIntersectionWithKeysReturnExpectedValuesWhenCustomComparatorProvided(): void
    {
        /** @var MapInterface<string> $map1 */
        $map1 = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'qoo' => 'ooq',
        ]);

        /** @var MapInterface<string> $map2 */
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
     * @psalm-param (Closure(mixed $a,mixed $b):int)|null $comparator
     * @dataProvider diffs
     */
    public function testCanDiff(array $initial, array $other, array $expected, ?callable $comparator): void
    {
        /** @psalm-suppress PossiblyInvalidArgument */
        $map = new GenericMap($initial);
        $diff = $map->diff(new GenericMap($other), $comparator);
        self::assertEquals($expected, $diff->toNativeArray());
    }

    /**
     * @psalm-return Generator<
     *     non-empty-string,
     *     array{
     *      0:array<string,mixed>,
     *      1:array<string,mixed>,
     *      2:array<string,mixed>,
     *      3:(Closure(mixed $a,mixed $b):int)|null
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
            [
                'baz' => 'qoo',
            ],
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
            [
                'value1' => $value1,
            ],
            [
                'value2' => $value2,
            ],
            null,
        ];

        $object1 = new stdClass();
        $object2 = new stdClass();

        yield 'object' => [
            [
                'object1' => $object1,
            ],
            [
                'object1' => $object1,
                'object2' => $object2,
            ],
            [
                'object2' => $object2,
            ],
            null,
        ];

        $object1 = new GenericObject(1);
        $object2 = new GenericObject(2);

        yield 'custom' => [
            [
                'object1' => $object1,
                'object2' => $object2,
            ],
            [
                'object2' => $object2,
            ],
            [
                'object1' => $object1,
            ],
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

        /** @var MapInterface<string> $map1 */
        $map1 = new GenericMap([
            'foo' => 'bar',
            'qoo' => 'ooq',
        ]);

        $map2 = new GenericMap([
            'qoo' => 'ooq',
        ]);

        self::assertEquals([
            'qoo' => 'ooq',
        ], $map1->intersectUserAssoc($map2, $valueComparator, $keyComparator)->toNativeArray());
    }
}
