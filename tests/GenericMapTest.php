<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use Boesing\TypedArrays\Asset\CallableObject;
use Boesing\TypedArrays\Asset\ComparableObject;
use Boesing\TypedArrays\Asset\GenericObject;
use DateTimeImmutable;
use Generator;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stringable;

use function array_keys;
use function array_map;
use function in_array;
use function json_encode;
use function ord;
use function strlen;
use function strnatcmp;
use function trim;

use const JSON_THROW_ON_ERROR;
use const PHP_INT_MAX;

final class GenericMapTest extends TestCase
{
    private int $iteration;

    /**
     * @psalm-return Generator<non-empty-string,array{
     *   0: array<non-empty-string,mixed>,
     *   1: pure-callable(non-empty-string, non-empty-string):non-empty-string,
     *   2: array<non-empty-string,mixed>
     * }>
     */
    public static function exchangeKeys(): Generator
    {
        yield 'change all keys' => [
            [
                'foo' => 'bar',
                'bar' => 'baz',
                'qoo' => 'ooq',
            ],
            function (string $key): string {
                switch ($key) {
                    case 'foo':
                        return 'bar';

                    case 'bar':
                        return 'baz';

                    case 'qoo':
                        return 'ooq';
                }

                return 'nope';
            },
            [
                'bar' => 'bar',
                'baz' => 'baz',
                'ooq' => 'ooq',
            ],
        ];

        yield 'change some keys' => [
            [
                'foo' => 'bar',
                'bar' => 'baz',
                'qoo' => 'ooq',
            ],
            function (string $key): string {
                switch ($key) {
                    case 'foo':
                        return 'fooo';

                    case 'bar':
                        return $key;

                    case 'qoo':
                        return 'ooq';
                }

                return 'nope';
            },
            [
                'fooo' => 'bar',
                'bar' => 'baz',
                'ooq' => 'ooq',
            ],
        ];

        yield 'change one key' => [
            [
                'foo' => 'bar',
                'bar' => 'baz',
                'qoo' => 'ooq',
            ],
            function (string $key): string {
                switch ($key) {
                    case 'bar':
                    case 'foo':
                        return $key;

                    case 'qoo':
                        return 'ooq';
                }

                return 'nope';
            },
            [
                'foo' => 'bar',
                'bar' => 'baz',
                'ooq' => 'ooq',
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->iteration = 0;
    }

    /**
     * @psalm-param  array<string,mixed>       $initial
     * @psalm-param  array<string,mixed>       $expected
     * @psalm-param  list<array<string,mixed>> $stack
     */
    #[DataProvider('mergeStacks')]
    public function testWillMerge(
        array $initial,
        array $expected,
        array $stack,
    ): void {
        /** @var MapInterface<string,mixed> $map */
        $map = new GenericMap($initial);

        /** @psalm-var list<MapInterface<string,mixed>> $stackOfMaps */
        $stackOfMaps = array_map(
            function (array $map): MapInterface {
                return new GenericMap($map);
            },
            $stack,
        );

        $merged = $map->merge(
            ...$stackOfMaps,
        );

        self::assertEquals($expected, $merged->toNativeArray());
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:array<string,mixed>,1:array<string,mixed>,2:list<array<string,mixed>>}>
     */
    public static function mergeStacks(): Generator
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

        yield 'none' => [
            ['foo' => 'bar'],
            ['foo' => 'bar'],
            [
                [],
            ],
        ];
    }

    /**
     * @psalm-param  array<string,mixed> $values
     * @psalm-param  (pure-callable(mixed,mixed):int)|null $callback
     * @psalm-param  array<string,mixed> $sorted
     */
    #[DataProvider('sorting')]
    public function testSortUsesCallback(array $values, callable|null $callback, array $sorted): void
    {
        $list = new GenericMap($values);
        self::assertEquals(
            $list->sort($callback)
                ->toNativeArray(),
            $sorted,
        );
    }

    /**
     * @psalm-return Generator<
     *     non-empty-string,
     *     array{0:array<string,mixed>,1:(pure-callable(mixed,mixed):int)|null,2:array<string,mixed>}
     * >
     */
    public static function sorting(): Generator
    {
        yield 'descending' => [
            [
                'foo' => 'bar',
                'bar' => 'baz',
                'baz' => 'qoo',
            ],
            function (string $a, string $b): int {
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

        $mapped = $map->map(function (GenericObject $object): int {
            return $object->id;
        });

        self::assertEquals(['first' => 1, 'second' => 2], $mapped->toNativeArray());
    }

    /**
     * @psalm-param array<string,mixed> $initial
     * @psalm-param list<mixed>         $expected
     * @psalm-param (pure-callable(mixed,mixed):int)|null $sorter
     */
    #[DataProvider('orderedLists')]
    public function testWillConvertToOrderedList(array $initial, array $expected, callable|null $sorter): void
    {
        $map  = new GenericMap($initial);
        $list = $map->toOrderedList($sorter);

        self::assertEquals($expected, $list->toNativeArray());
    }

    /**
     * @psalm-return Generator<
     *     non-empty-string,
     *     array{0:array<string,mixed>,1:list<mixed>,2:(pure-callable(mixed,mixed):int)|null}>
     */
    public static function orderedLists(): Generator
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
            function (int $a, int $b): int {
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

        self::assertEquals(['foo' => 'bar'], $map1->intersectAssoc($map2, function (string $a, string $b): int {
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
        ], $map1->intersectUsingKeys($map2, function (string $a, string $b): int {
            return strlen($a) <=> strlen($b);
        })->toNativeArray());
    }

    /**
     * @param array<string,mixed> $initial
     * @param array<string,mixed> $other
     * @param array<string,mixed> $expected
     * @psalm-param (pure-callable(mixed,mixed):int)|null $comparator
     */
    #[DataProvider('diffs')]
    public function testCanDiff(array $initial, array $other, array $expected, callable|null $comparator): void
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
     *      3:(pure-callable(mixed,mixed):int)|null
     *     }
     * >
     */
    public static function diffs(): Generator
    {
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

        $now = new DateTimeImmutable();

        yield 'datetime' => [
            [
                'now' => $now,
                '10seconds' => $now->modify('+10 seconds'),
                '20seconds' => $now->modify('+20 seconds'),
            ],
            [
                'now' => $now,
                '10seconds' => $now->modify('+10 seconds'),
                '20seconds' => $now->modify('+20 seconds'),
                '30seconds' => $now->modify('+30 seconds'),
            ],
            [
                '30seconds' => $now->modify('+30 seconds'),
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
            function (object $a, object $b): int {
                return $a->id <=> $b->id;
            },
        ];
    }

    public function testCanIntersectWithUserFunctions(): void
    {
        $keyComparator = function (string $a, string $b): int {
            return $a <=> $b;
        };

        $valueComparator = function (string $a, string $b): int {
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
                ->toNativeArray(),
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
     * @template     TValue
     * @psalm-param array<string,TValue> $initial
     * @psalm-param pure-callable(TValue $value):bool $callback
     * @psalm-param array<string,TValue> $filteredExpectation
     * @psalm-param array<string,TValue> $unfilteredExpectation
     */
    #[DataProvider('partitions')]
    public function testPartitioningReturnsTwoMapsWithExpectedValues(
        array $initial,
        callable $callback,
        array $filteredExpectation,
        array $unfilteredExpectation,
    ): void {
        $map = new GenericMap($initial);

        [$filtered, $unfiltered] = $map->partition($callback);
        self::assertEquals($filtered->toNativeArray(), $filteredExpectation);
        self::assertEquals($unfiltered->toNativeArray(), $unfilteredExpectation);
    }

    /**
     * @return Generator<
     *     string,
     *     array{0:array<string,mixed>,1:pure-callable(mixed $value):bool,2:array<string,mixed>,3:array<string,mixed>}
     * >
     */
    public static function partitions(): Generator
    {
        yield 'all filtered' => [
            [
                'foo' => 'bar',
                'bar' => 'baz',
                'qoo' => 'ooq',
            ],
            function (): bool {
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
            function (): bool {
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
            function (string $value): bool {
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
            function (string $value): bool {
                return $value === 'bar';
            },
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

        $grouped = $map->group(function (GenericObject $object): string {
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

    public function testWillGroupValueIntoMultipleGroups(): void
    {
        $map = new GenericMap([
            'foo' => $object1 = new GenericObject(1),
        ]);

        $grouped = $map->group(fn () => ['a', 'b']);
        self::assertTrue($grouped->has('a'));
        self::assertTrue($grouped->has('b'));
        self::assertTrue($grouped->get('a')->contains($object1));
        self::assertTrue($grouped->get('b')->contains($object1));
    }

    /**
     * @template     T
     * @psalm-param array<string,T> $elements
     * @psalm-param pure-callable(T):bool $callback
     */
    #[DataProvider('satisfactions')]
    public function testAllElementsWillSatisfyCallback(array $elements, callable $callback): void
    {
        $map = new GenericMap($elements);
        self::assertTrue($map->allSatisfy($callback));
    }

    public function testElementsWontSatisfyCallback(): void
    {
        $map = new GenericMap(['foo' => 'bar']);

        self::assertFalse($map->allSatisfy(function (): bool {
            return false;
        }));
    }

    public function testEmptyMapWillSatisfyCallback(): void
    {
        $map = new GenericMap([]);
        self::assertTrue($map->allSatisfy(function (): bool {
            return false;
        }));
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:array<non-empty-string,mixed>,1:pure-callable(mixed):bool}>
     */
    public static function satisfactions(): Generator
    {
        yield 'only 1' => [
            [
                'one' => 1,
                'another one' => 1,
            ],
            function (int $value): bool {
                return $value === 1;
            },
        ];

        yield 'all same string length' => [
            [
                'foo' => 'foo',
                'bar' => 'foo',
            ],
            function (string $value): bool {
                return strlen($value) === 3;
            },
        ];
    }

    /**
     * @template     T
     * @psalm-param array<non-empty-string,T> $data
     * @psalm-param pure-callable(T):bool           $callback
     */
    #[DataProvider('existenceTests')]
    public function testWillFindExistenceOfEntry(array $data, callable $callback, bool $exists): void
    {
        $map = new GenericMap($data);

        self::assertSame($exists, $map->exists($callback));
    }

    public function testEmptyMapWontFindExistence(): void
    {
        $map = new GenericMap();
        self::assertFalse($map->exists(function (): bool {
            return true;
        }));
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:array<non-empty-string,mixed>,1:pure-callable(mixed):bool,2:bool}>
     */
    public static function existenceTests(): Generator
    {
        yield 'simple' => [
            [
                'one' => 1,
                'two' => 2,
                'another one' => 1,
            ],
            function (int $value): bool {
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
            function (int $value): bool {
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
            function (string $value): bool {
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

    public function testJsonSerializeOnEmptyMapReturnsNull(): void
    {
        $instance = new GenericMap();
        self::assertEquals('null', json_encode($instance, JSON_THROW_ON_ERROR));
    }

    public function testJsonSerializeWillGenerateMapOfEntries(): void
    {
        $list = new GenericMap([
            'one' => 1,
            'two' => 2,
            'foo' => 'foo',
            'three' => 3,
        ]);

        self::assertEquals('{"one":1,"two":2,"foo":"foo","three":3}', json_encode($list, JSON_THROW_ON_ERROR));
    }

    public function testForAllIsExecutedForAllEntries(): void
    {
        $map = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'qoo' => 'ooq',
        ]);

        $callable = new CallableObject(['bar', 'foo'], ['baz', 'bar'], ['ooq', 'qoo']);
        $map->forAll(fn ($value, $key) => $callable($value, $key))->execute();
    }

    public function testWillGenerateErrorCollectionWhileExecutingForAll(): void
    {
        $map = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'qoo' => 'ooq',
        ]);

        $callable = function (string $value, string $key): void {
            $this->iteration++;

            if ($key === 'bar') {
                throw new RuntimeException($key);
            }
        };

        $errorCollection = null;

        try {
            $map->forAll($callable)->execute();
        } catch (MappedErrorCollection $errorCollection) {
        }

        self::assertEquals($map->count(), $this->iteration);
        self::assertInstanceOf(MappedErrorCollection::class, $errorCollection);
        $errors = $errorCollection->errors();
        self::assertCount(1, $errors);
        self::assertInstanceOf(RuntimeException::class, $errors->get('bar'));
    }

    public function testWillGenerateErrorCollectionWhileExecutingForAllButStopsExecutionOnError(): void
    {
        $map = new GenericMap([
            'foo' => 'bar',
            'bar' => 'baz',
            'qoo' => 'ooq',
        ]);

        $callable = function (string $value, string $key): void {
            $this->iteration++;
            if ($key === 'bar') {
                throw new RuntimeException($key);
            }
        };

        $errorCollection = null;

        try {
            $map->forAll($callable)->stopOnError()->execute();
        } catch (MappedErrorCollection $errorCollection) {
        }

        self::assertNotEquals($map->count(), $this->iteration);
        self::assertEquals(2, $this->iteration);
        self::assertInstanceOf(MappedErrorCollection::class, $errorCollection);
        $errors = $errorCollection->errors();
        self::assertCount(1, $errors);
        self::assertInstanceOf(RuntimeException::class, $errors->get('bar'));
    }

    public function testForAllPromiseWillSuppressErrors(): void
    {
        $map = new GenericMap(['foo' => 'bar']);

        $callback = function (): void {
            throw new RuntimeException();
        };
        $map->forAll($callback)->suppressErrors();

        $this->expectException(RuntimeException::class);
        $map->forAll($callback)->execute();
    }

    public function testForAllPromiseWillExecuteFinallyMethodBeforeThrowingException(): void
    {
        $callbackInvoked = false;
        $callback        = function () use (&$callbackInvoked): void {
            $callbackInvoked = true;
        };

        $map = new GenericMap(['foo' => 'bar']);

        $runtimeExceptionCaught = false;
        try {
            $map->forAll(function (): void {
                throw new RuntimeException();
            })->finally($callback);
        } catch (RuntimeException) {
            $runtimeExceptionCaught = true;
        }

        self::assertTrue($runtimeExceptionCaught);
        self::assertTrue($callbackInvoked);
    }

    public function testWillSortKeysAlphabeticallyWhenNoSorterIsPassed(): void
    {
        $map = new GenericMap([
            'e' => '',
            'd' => '',
            'c' => '',
            'b' => '',
            'a' => '',
        ]);

        $keysSorted = $map->sortByKey();
        self::assertEquals(['a', 'b', 'c', 'd', 'e'], array_keys($keysSorted->toNativeArray()));
    }

    public function testWillSortKeysByProvidedCustomSorter(): void
    {
        $data = [
            'a' => '',
            'b' => '',
            'c' => '',
            'd' => '',
            'e' => '',
        ];

        $map = new GenericMap($data);

        $sorter = function (string $a, string $b): int {
            return ord($b) <=> ord($a);
        };

        $keysSorted = $map->sortByKey($sorter);

        self::assertEquals(['e', 'd', 'c', 'b', 'a'], array_keys($keysSorted->toNativeArray()));
    }

    #[DataProvider('joinableValues')]
    public function testCanJoin(string|Stringable $joinableValue): void
    {
        $map = new GenericMap(['foo' => $joinableValue]);

        self::assertEquals((string) $joinableValue, $map->join());
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:string|Stringable}>
     */
    public static function joinableValues(): Generator
    {
        yield 'simple string' => ['fooo bar'];

        yield 'stringable object' => [
            new class implements Stringable {
                public function __toString(): string
                {
                    return 'string from __toString method';
                }
            },
        ];
    }

    public function testWillPassthruJoinError(): void
    {
        $map = new GenericMap([
            'foo' => new stdClass(),
        ]);

        $this->expectException(RuntimeException::class);
        $map->join();
    }

    public function testWillJoinValuesWithSeperator(): void
    {
        $map = new GenericMap([
            'foo' => 'foo',
            'bar' => 'bar',
            'baz' => 'baz',
        ]);

        self::assertSame('foo:bar:baz', $map->join(':'));
    }

    /**
     * @psalm-param array<non-empty-string,mixed>    $initial
     * @psalm-param pure-callable(non-empty-string, mixed):non-empty-string $keyGenerator
     * @psalm-param array<non-empty-string,mixed>    $expected
     */
    #[DataProvider('exchangeKeys')]
    public function testWillExchangeKeys(array $initial, callable $keyGenerator, array $expected): void
    {
        $map       = new GenericMap($initial);
        $exchanged = $map->keyExchange($keyGenerator);
        self::assertEquals($expected, $exchanged->toNativeArray());
    }

    public function testWillDetectDuplicatedKeys(): void
    {
        $map = new GenericMap(['foo' => 'bar', 'bar' => 'baz']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provided key generator generates the same key "foo" multiple times.');
        $map->keyExchange(function (): string {
            return 'foo';
        });
    }

    public function testWillReduceMap(): void
    {
        $list = new GenericMap([
            'foo' => 1,
            'bar' => 2,
            'baz' => 3,
            'qoo' => 4,
            'ooq' => 5,
        ]);

        self::assertSame(
            15,
            $list->reduce(fn (int $carry, int $value) => $value + $carry, 0),
            'A sum of all values were expected.',
        );
        self::assertSame(
            120,
            $list->reduce(fn (int $carry, int $value) => $carry === 0 ? $value : $value * $carry, 0),
            'Expected that all values are being multiplied with each other',
        );
    }

    public function testReduceWillReturnInitialValueOnEmptyList(): void
    {
        $list = new GenericMap([]);
        self::assertSame(PHP_INT_MAX, $list->reduce(fn () => 1, PHP_INT_MAX));
    }
}
