<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use Boesing\TypedArrays\Asset\CallableObject;
use Boesing\TypedArrays\Asset\ComparableObject;
use Boesing\TypedArrays\Asset\GenericObject;
use DateTimeImmutable;
use Generator;
use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stringable;
use Webmozart\Assert\Assert;

use function array_fill;
use function array_map;
use function array_reverse;
use function assert;
use function chr;
use function in_array;
use function is_int;
use function json_encode;
use function md5;
use function mt_rand;
use function range;
use function spl_object_hash;
use function strlen;
use function strnatcmp;

use const JSON_THROW_ON_ERROR;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class GenericOrderedListTest extends TestCase
{
    private int $iteration = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iteration = 0;
    }

    /**
     * @psalm-param  list<mixed> $values
     * @psalm-param  (pure-callable(mixed,mixed):int)|null $callback
     * @psalm-param  list<mixed> $sorted
     */
    #[DataProvider('sorting')]
    public function testSortUsesCallback(array $values, callable|null $callback, array $sorted): void
    {
        $list = new GenericOrderedList($values);
        self::assertEquals(
            $list->sort($callback)
                ->toNativeArray(),
            $sorted,
        );
    }

    /**
     * @psalm-param  list<mixed>       $initial
     * @psalm-param  list<mixed>       $expected
     * @psalm-param  list<list<mixed>> $stack
     */
    #[DataProvider('mergeStacks')]
    public function testWillMerge(
        array $initial,
        array $expected,
        array $stack,
    ): void {
        $list = new GenericOrderedList($initial);

        $merged = $list->merge(
            ...array_map(
                function (array $list): OrderedListInterface {
                    return new GenericOrderedList($list);
                },
                $stack,
            ),
        );
        self::assertEquals($expected, $merged->toNativeArray());
    }

    /**
     * @psalm-return Generator<
     *     non-empty-string,
     *     array{0:list<mixed>,1:(pure-callable(mixed,mixed):int)|null,2:list<mixed>}>
     */
    public static function sorting(): Generator
    {
        yield 'descending' => [
            [
                'foo',
                'bar',
                'baz',
            ],
            function (string $a, string $b): int {
                return strnatcmp($b, $a);
            },
            [
                'foo',
                'baz',
                'bar',
            ],
        ];

        yield 'ascending natural' => [
            [
                2,
                3,
                1,
            ],
            null,
            [
                1,
                2,
                3,
            ],
        ];
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:list<mixed>,1:list<mixed>,2:list<list<mixed>>}>
     */
    public static function mergeStacks(): Generator
    {
        yield 'single' => [
            [
                'foo',
                'bar',
            ],
            [
                'foo',
                'bar',
                'baz',
                'foo',
            ],
            [
                ['baz', 'foo'],
            ],
        ];

        yield 'multiple' => [
            [
                'foo',
                'bar',
            ],
            [
                'foo',
                'bar',
                'baz',
                'foo',
                'baz',
                'foo',
            ],
            [
                ['baz', 'foo'],
                ['baz', 'foo'],
            ],
        ];

        yield 'none' => [
            [
                'foo',
                'bar',
            ],
            [
                'foo',
                'bar',
            ],
            [
                [],
            ],
        ];
    }

    public function testWillMapByUsingCallable(): void
    {
        $list = new GenericOrderedList([
            97,
            98,
            99,
        ]);

        $mapped = $list->map(function (int $value): string {
            return chr($value);
        });

        self::assertNotEquals(
            $mapped,
            $list,
        );

        self::assertEquals([
            'a',
            'b',
            'c',
        ], $mapped->toNativeArray());
    }

    public function testCanAddAnotherElement(): void
    {
        /** @var OrderedListInterface<int> $list */
        $list = new GenericOrderedList([
            1,
            2,
            3,
        ]);

        $added = $list->add(4);

        self::assertNotEquals($list, $added);
        self::assertEquals([
            1,
            2,
            3,
            4,
        ], $added->toNativeArray());
    }

    public function testCanReceiveSpecificItem(): void
    {
        $list = new GenericOrderedList(['foo']);

        self::assertEquals(
            'foo',
            $list->at(0),
        );
    }

    public function testReturnsNullWhenSpecificItemNotFound(): void
    {
        $list = new GenericOrderedList([]);

        $this->expectException(OutOfBoundsException::class);
        $list->at(0);
    }

    /**
     * @psalm-param  list<mixed> $initial
     * @psalm-param  list<mixed> $other
     * @psalm-param  list<mixed> $expected
     * @psalm-param (pure-callable(mixed,mixed):int)|null $comparator
     */
    #[DataProvider('diffs')]
    public function testDiffWillDetectExpectedDifferences(
        array $initial,
        array $other,
        array $expected,
        callable|null $comparator,
    ): void {
        $list = new GenericOrderedList($initial);
        $diff = $list->diff(new GenericOrderedList($other), $comparator);

        self::assertEquals($expected, $diff->toNativeArray());
    }

    /**
     * @psalm-return Generator<
     *     non-empty-string,
     *     array{0:list<mixed>,1:list<mixed>,2:list<mixed>,3:(pure-callable(mixed, mixed):int)|null}
     * >
     */
    public static function diffs(): Generator
    {
        yield 'simple' => [
            [
                'foo',
                'bar',
                'baz',
            ],
            [
                'foo',
                'bar',
            ],
            ['baz'],
            null,
        ];

        $now = new DateTimeImmutable();

        yield 'datetime' => [
            [
                $now,
                $now->modify('+10 seconds'),
                $now->modify('+20 seconds'),
            ],
            [
                $now,
                $now->modify('+10 seconds'),
                $now->modify('+20 seconds'),
                $now->modify('+30 seconds'),
            ],
            [
                $now->modify('+30 seconds'),
            ],
            null,
        ];

        $value1 = new ComparableObject(1);
        $value2 = new ComparableObject(2);

        yield 'comparator' => [
            [
                $value1,
                $value2,
            ],
            [$value1],
            [$value2],
            null,
        ];

        $object1 = new stdClass();
        $object2 = new stdClass();

        yield 'object' => [
            [$object1],
            [
                $object1,
                $object2,
            ],
            [$object2],
            null,
        ];

        $object1 = new GenericObject(1);
        $object2 = new GenericObject(2);

        yield 'custom' => [
            [
                $object1,
                $object2,
            ],
            [$object2],
            [$object1],
            function (object $a, object $b): int {
                return $a->id <=> $b->id;
            },
        ];
    }

    /**
     * @psalm-param  list<mixed> $initial
     * @psalm-param  list<mixed> $other
     * @psalm-param  list<mixed> $expected
     * @psalm-param (pure-callable(mixed,mixed):int)|null $comparator
     */
    #[DataProvider('intersections')]
    public function testCanDetectIntersections(
        array $initial,
        array $other,
        array $expected,
        callable|null $comparator,
    ): void {
        $collection   = new GenericOrderedList($initial);
        $intersection = $collection->intersect(new GenericOrderedList($other), $comparator);

        self::assertEquals($expected, $intersection->toNativeArray());
    }

    /**
     * @psalm-return Generator<string,array{0:list<mixed>,1:list<mixed>,2:list<mixed>,3:(pure-callable(mixed $a,mixed
     *               $b):int)|null}>
     */
    public static function intersections(): Generator
    {
        $now = new DateTimeImmutable();

        yield 'simple' => [
            [
                'foo',
                'bar',
            ],
            [
                'bar',
                'baz',
            ],
            ['bar'],
            null,
        ];

        yield 'datetime' => [
            [
                $now,
                $tenSeconds    = $now->modify('+10 seconds'),
                $twentySeconds = $now->modify('+20 seconds'),
            ],
            [
                $now,
                $tenSeconds,
                $twentySeconds,
                $now->modify('+30 seconds'),
            ],
            [
                $now,
                $tenSeconds,
                $twentySeconds,
            ],
            null,
        ];

        $value1 = new ComparableObject(1);
        $value2 = new ComparableObject(2);

        yield 'comparator' => [
            [
                $value1,
                $value2,
            ],
            [$value1],
            [$value1],
            null,
        ];

        $object1 = new stdClass();
        $object2 = new stdClass();

        yield 'object' => [
            [$object1],
            [
                $object1,
                $object2,
            ],
            [$object1],
            null,
        ];

        $object1 = new GenericObject(1);
        $object2 = new GenericObject(2);

        yield 'custom' => [
            [
                $object1,
                $object2,
            ],
            [$object2],
            [$object2],
            function (object $a, object $b): int {
                return $a->id <=> $b->id;
            },
        ];
    }

    public function testCanMap(): void
    {
        $object1 = new GenericObject(1);
        $object2 = new GenericObject(2);

        $list = new GenericOrderedList([
            $object1,
            $object2,
        ]);

        $mapped = $list->map(function (GenericObject $object): string {
            return spl_object_hash($object);
        });

        self::assertEquals([
            spl_object_hash($object1),
            spl_object_hash($object2),
        ], $mapped->toNativeArray());
    }

    public function testCanConvertToMap(): void
    {
        $object1 = new GenericObject(1);
        $object2 = new GenericObject(2);

        $list = new GenericOrderedList([
            $object1,
            $object2,
        ]);

        $mapped = $list->toMap(function (GenericObject $object): string {
            $hash = spl_object_hash($object);
            Assert::stringNotEmpty($hash);

            return $hash;
        });

        self::assertEquals([
            spl_object_hash($object1) => $object1,
            spl_object_hash($object2) => $object2,
        ], $mapped->toNativeArray());
    }

    public function testCanFilter(): void
    {
        $object1 = new GenericObject(1);
        $object2 = new GenericObject(2);

        $list = new GenericOrderedList([
            $object1,
            $object2,
        ]);

        $filtered = $list->filter(function (GenericObject $object) use ($object2): bool {
            return $object !== $object2;
        });

        self::assertEquals([$object1], $filtered->toNativeArray());
    }

    public function testCanRemove(): void
    {
        $object1 = new GenericObject(1);
        $object2 = new GenericObject(2);

        $list = new GenericOrderedList([
            $object1,
            $object2,
        ]);

        $list = $list->removeElement($object2);

        self::assertEquals([$object1], $list->toNativeArray());
    }

    /**
     * @psalm-param  list<mixed> $initial
     * @psalm-param  list<mixed> $expected
     * @psalm-param (pure-callable(mixed):non-empty-string)|null $unificationIdentifierGenerator
     */
    #[DataProvider('deduplications')]
    public function testCanRemoveDuplicates(
        array $initial,
        array $expected,
        callable|null $unificationIdentifierGenerator,
    ): void {
        $list = new GenericOrderedList($initial);

        $unified = $list->unify($unificationIdentifierGenerator);

        self::assertEquals($expected, $unified->toNativeArray());
    }

    public function testUsesCallbackOnDeduplication(): void
    {
        $list           = new GenericOrderedList([1, 2, 3, 1]);
        $callbackCalled = false;

        /**
         * @psalm-suppress InvalidArgument
         */
        $list->unify(null, function () use (&$callbackCalled): void {
            $callbackCalled = true;
        });
        self::assertTrue($callbackCalled);
    }

    public function testCallbackOnDeduplicationIsOnlyCalledForDuplicates(): void
    {
        $list           = new GenericOrderedList([1, 2, 3, 1, 1, 1]);
        $callbackCalled = 0;

        $list->unify(null, function (int $duplicate, int $number) use (&$callbackCalled): int {
            self::assertEquals($duplicate, $number);
            assert(is_int($callbackCalled));
            $callbackCalled++;

            return $number;
        });
        self::assertEquals(3, $callbackCalled);
    }

    /**
     * @psalm-return Generator<string,array{0:list<mixed>,1:list<mixed>,2:(pure-callable(mixed):non-empty-string)|null}>
     */
    public static function deduplications(): Generator
    {
        yield 'integers' => [
            [
                1,
                1,
                1,
            ],
            [1],
            null,
        ];

        yield 'strings' => [
            [
                'foo',
                'bar',
                'foo',
            ],
            [
                'foo',
                'bar',
            ],
            null,
        ];

        yield 'booleans' => [
            [
                false,
                true,
                false,
            ],
            [
                false,
                true,
            ],
            null,
        ];

        $object1            = new GenericObject(1);
        $object2            = new GenericObject(2);
        $object3WithIdFrom1 = new GenericObject(1);

        yield 'objects' => [
            [
                $object1,
                $object2,
                $object1,
            ],
            [
                $object1,
                $object2,
            ],
            null,
        ];

        yield 'objects with unique identifier generator' => [
            [
                $object1,
                $object2,
                $object3WithIdFrom1,
            ],
            [
                $object1,
                $object2,
            ],
            function (GenericObject $object): string {
                $hash = md5((string) $object->id);
                Assert::stringNotEmpty($hash);

                return $hash;
            },
        ];
    }

    public function testCount(): void
    {
        self::assertCount(3, new GenericOrderedList([1, 2, 3]));
        self::assertCount(2, new GenericOrderedList(['foo', 'bar']));
    }

    public function testCanDetectEmptyElements(): void
    {
        $list = new GenericOrderedList([]);
        self::assertTrue($list->isEmpty());
    }

    public function testCanDetectElementsInList(): void
    {
        $list = new GenericOrderedList([100]);
        self::assertFalse($list->isEmpty());
    }

    public function testFirstAndLastElementEqualsWhenOnlyOneElement(): void
    {
        $list = new GenericOrderedList([100]);
        self::assertEquals(100, $list->first());
        self::assertEquals(100, $list->last());
    }

    public function testFirstAndLastElementReturnsIdenticalObject(): void
    {
        $object1 = new GenericObject(1);
        $object2 = new GenericObject(2);
        $list    = new GenericOrderedList([$object1, $object2]);
        self::assertEquals($object1, $list->first());
        self::assertEquals($object2, $list->last());
    }

    public function testFirstThrowsOutOfBoundsExceptionWhenListIsEmpty(): void
    {
        $list = new GenericOrderedList([]);
        $this->expectException(OutOfBoundsException::class);
        $list->first();
    }

    public function testLastThrowsOutOfBoundsExceptionWhenListIsEmpty(): void
    {
        $list = new GenericOrderedList([]);
        $this->expectException(OutOfBoundsException::class);
        $list->last();
    }

    public function testContainsDoesExactMatch(): void
    {
        /** @var OrderedListInterface<string> $list */
        $list = new GenericOrderedList(['1 ', '2']);
        self::assertFalse($list->contains('1'));
    }

    public function testIteratorContainsExpectedData(): void
    {
        /** @var OrderedListInterface<string> $list */
        $list     = new GenericOrderedList(['1 ', '2']);
        $expected = 0;
        foreach ($list as $integer => $string) {
            self::assertEquals($expected++, $integer);
            self::assertEquals($list->at($integer), $string);
        }
    }

    public function testToMapConversionErrorsOnIntegerishKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $list = new GenericOrderedList([1, 2, 3]);
        $list->toMap(function (int $value): string {
            return (string) $value;
        });
    }

    /**
     * @template     TValue
     * @psalm-param  list<TValue> $initial
     * @psalm-param  TValue       $fillUp
     */
    #[DataProvider('invalidStartIndices')]
    public function testFillWillThrowExceptionWhenStartIndexIsInvalid(
        int $startIndex,
        array $initial,
        $fillUp,
        string $expectedExceptionMessage,
    ): void {
        $list = new GenericOrderedList($initial);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $list->fill($startIndex, mt_rand(1, 10), $fillUp);
    }

    /**
     * @template     TValue
     * @psalm-param  TValue $value
     */
    #[DataProvider('scalarFillValues')]
    public function testFillAppendsScalarValues(int $amount, $value): void
    {
        self::assertIsScalar($value);
        /** @var OrderedListInterface<TValue> $list */
        $list = new GenericOrderedList([]);
        $list = $list->fill(0, $amount, $value);
        self::assertEquals(array_fill(0, $amount, $value), $list->toNativeArray());
    }

    /**
     * @template     mixed
     * @psalm-return Generator<string,array{0:int,1:list<mixed>,2:mixed,3:non-empty-string}>
     */
    public static function invalidStartIndices(): Generator
    {
        yield 'negative' => [
            -1,
            [],
            0,
            'Given $startIndex must be greater than or equal to',
        ];

        yield 'non continues index' => [
            1,
            [],
            0,
            'to keep the list a continious list.',
        ];

        yield 'non continues index #2' => [
            10,
            [0, 1, 2],
            3,
            'to keep the list a continious list.',
        ];
    }

    /**
     * @psalm-return Generator<string,array{0:int,1:mixed}>
     */
    public static function scalarFillValues(): Generator
    {
        yield 'int' => [
            1,
            0,
        ];

        yield 'string' => [
            99,
            'foo',
        ];

        yield 'float' => [
            10,
            0.1,
        ];

        yield 'true' => [
            8,
            true,
        ];

        yield 'false' => [
            50,
            false,
        ];
    }

    public function testFillUsesCallbackToGenerateValue(): void
    {
        $callback = function (int $index): string {
            return chr($index + 65);
        };

        /** @var OrderedListInterface<string> $abc */
        $abc = new GenericOrderedList([]);
        $abc = $abc->fill(0, 26, $callback);

        self::assertEquals([
            'A',
            'B',
            'C',
            'D',
            'E',
            'F',
            'G',
            'H',
            'I',
            'J',
            'K',
            'L',
            'M',
            'N',
            'O',
            'P',
            'Q',
            'R',
            'S',
            'T',
            'U',
            'V',
            'W',
            'X',
            'Y',
            'Z',
        ], $abc->toNativeArray());
    }

    public function testSliceIsImmutable(): void
    {
        $instance    = new GenericOrderedList([]);
        $newInstance = $instance->slice(0);

        self::assertNotSame($instance, $newInstance);
    }

    public function testSliceCanHandlePositiveOffset(): void
    {
        $instance = new GenericOrderedList([0, 1, 2, 3]);
        $sliced   = $instance->slice(2);

        self::assertEquals([
            2,
            3,
        ], $sliced->toNativeArray());
    }

    public function testSliceCanHandleNegativeOffset(): void
    {
        $instance = new GenericOrderedList([0, 1, 2, 3]);
        $sliced   = $instance->slice(-2);

        self::assertEquals([
            2,
            3,
        ], $sliced->toNativeArray());
    }

    public function testSliceCanLimitResult(): void
    {
        $instance = new GenericOrderedList([0, 1, 2, 3]);
        $sliced   = $instance->slice(1, 2);

        self::assertEquals([
            1,
            2,
        ], $sliced->toNativeArray());
    }

    public function testSliceCanLimitResultForNegativeOffset(): void
    {
        $instance = new GenericOrderedList([0, 1, 2, 3]);
        $sliced   = $instance->slice(-2, 1);

        self::assertEquals([2], $sliced->toNativeArray());
    }

    /**
     * @template     TValue
     * @psalm-param  list<TValue>    $initial
     * @psalm-param  pure-callable(TValue $value):bool $callback
     */
    #[DataProvider('findOutOfBoundExceptions')]
    public function testFindThrowsOutOfBoundsExceptionWhenValueNotFound(array $initial, callable $callback): void
    {
        $instance = new GenericOrderedList($initial);
        $this->expectException(OutOfBoundsException::class);
        $instance->find($callback);
    }

    /**
     * @psalm-return Generator<string,array{0:list<mixed>,1:(pure-callable(mixed $value):bool)}>
     */
    public static function findOutOfBoundExceptions(): Generator
    {
        yield 'empty list' => [
            [],
            function (): bool {
                return true;
            },
        ];

        yield 'non-empty list but finding impossible' => [
            [1, 2, 3],
            function (): bool {
                return false;
            },
        ];
    }

    public function testFindWillLocateFirstMatch(): void
    {
        $list = new GenericOrderedList([
            ['id' => 1, 'position' => 1],
            ['id' => 1, 'position' => 2],
        ]);

        $value = $list->find(function (array $value) {
            return $value['id'] === 1;
        });

        self::assertEquals([
            'id' => 1,
            'position' => 1,
        ], $value);
    }

    /**
     * @template     TValue
     * @psalm-param  list<TValue> $initial
     * @psalm-param  pure-callable(TValue $value):bool $callback
     * @psalm-param  list<TValue> $filteredExpectation
     * @psalm-param  list<TValue> $unfilteredExpectation
     */
    #[DataProvider('partitions')]
    public function testPartitioningReturnsTwoMapsWithExpectedValues(
        array $initial,
        callable $callback,
        array $filteredExpectation,
        array $unfilteredExpectation,
    ): void {
        $map = new GenericOrderedList($initial);

        [$filtered, $unfiltered] = $map->partition($callback);
        self::assertEquals($filtered->toNativeArray(), $filteredExpectation);
        self::assertEquals($unfiltered->toNativeArray(), $unfilteredExpectation);
    }

    /**
     * @return Generator<string,array{0:list<mixed>,1:pure-callable(mixed $value):bool,2:list<mixed>,3:list<mixed>}>
     */
    public static function partitions(): Generator
    {
        yield 'all filtered' => [
            [
                'bar',
                'baz',
                'ooq',
            ],
            function (): bool {
                return true;
            },
            [
                'bar',
                'baz',
                'ooq',
            ],
            [],
        ];

        yield 'none filtered' => [
            [
                'bar',
                'baz',
                'ooq',
            ],
            function (): bool {
                return false;
            },
            [],
            [
                'bar',
                'baz',
                'ooq',
            ],
        ];

        yield 'some filtered' => [
            [
                'bar',
                'baz',
                'ooq',
            ],
            function (string $value): bool {
                return in_array($value, ['baz', 'ooq'], true);
            },
            [
                'baz',
                'ooq',
            ],
            ['bar'],
        ];
    }

    public function testWillGroupValuesToNewInstancesOfInitialInstance(): void
    {
        $list = new GenericOrderedList([
            $object1 = new GenericObject(1),
            $object2 = new GenericObject(2),
            $object3 = new GenericObject(3),
        ]);

        $grouped = $list->group(fn (GenericObject $object): string => $object->id % 2 ? 'a' : 'b');

        self::assertTrue($grouped->has('a'));
        self::assertTrue($grouped->has('b'));

        $a = $grouped->get('a');
        self::assertCount(2, $a);
        self::assertEquals($object1, $a->at(0));
        self::assertEquals($object3, $a->at(1));
        $b = $grouped->get('b');
        self::assertCount(1, $b);
        self::assertEquals($object2, $b->at(0));
    }

    /**
     * @template     T
     * @psalm-param  list<T> $data
     * @psalm-param  pure-callable(T):bool $callback
     */
    #[DataProvider('satisfactions')]
    public function testAllWillSatisfyCallback(array $data, callable $callback): void
    {
        $list = new GenericOrderedList($data);
        self::assertTrue($list->allSatisfy($callback));
    }

    public function testEmptyListWillSatisfyAnyCallback(): void
    {
        $list = new GenericOrderedList();
        self::assertTrue($list->allSatisfy(function (): bool {
            return false;
        }));
    }

    public function testWontSatisfyCallback(): void
    {
        $list = new GenericOrderedList(['foo']);
        self::assertFalse($list->allSatisfy(function (): bool {
            return false;
        }));
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:list<mixed>,1:pure-callable(mixed):bool}>
     */
    public static function satisfactions(): Generator
    {
        yield 'only 1' => [
            [
                1,
                1,
                1,
            ],
            function (int $value): bool {
                return $value === 1;
            },
        ];

        yield 'same string length' => [
            [
                'foo',
                'bar',
                'baz',
            ],
            function (string $value): bool {
                return strlen($value) === 3;
            },
        ];
    }

    /**
     * @template T
     * @psalm-param list<T> $list
     * @psalm-param pure-callable(T):bool $callback
     */
    #[DataProvider('existenceTests')]
    public function testWillFindExistenceOfEntry(array $list, callable $callback, bool $exists): void
    {
        $list = new GenericOrderedList($list);

        self::assertSame($exists, $list->exists($callback));
    }

    public function testEmptyListWontFindExistence(): void
    {
        $list = new GenericOrderedList();
        self::assertFalse($list->exists(function (): bool {
            return true;
        }));
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:list<mixed>,1:pure-callable(mixed):bool,2:bool}>
     */
    public static function existenceTests(): Generator
    {
        yield 'simple' => [
            [
                1,
                2,
                1,
            ],
            function (int $value): bool {
                return $value === 2;
            },
            true,
        ];

        yield 'loose comparison' => [
            [
                1,
                2,
                3,
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
                'foo',
                'bar',
                'baz',
            ],
            function (string $value): bool {
                return $value === 'qoo';
            },
            false,
        ];
    }

    public function testJsonSerializeOnEmptyListReturnsEmptyList(): void
    {
        $instance = new GenericOrderedList();
        self::assertEquals('[]', json_encode($instance, JSON_THROW_ON_ERROR));
    }

    public function testJsonSerializeWillGenerateListOfEntries(): void
    {
        $list = new GenericOrderedList([
            1,
            2,
            'foo',
            3,
        ]);

        self::assertEquals('[1,2,"foo",3]', json_encode($list, JSON_THROW_ON_ERROR));
    }

    public function testRemovalOfEntryWillStillJsonSerializeListOfEntries(): void
    {
        $list = new GenericOrderedList([
            1,
            2,
            'foo',
            3,
        ]);

        /** @psalm-suppress InvalidArgument Might be a psalm bug */
        $list = $list->removeElement(2);

        self::assertEquals('[1,"foo",3]', json_encode($list, JSON_THROW_ON_ERROR));
    }

    /**
     * @dataProvider listValues
     */
    public function testCanDetectExistingIndices(mixed $value): void
    {
        $list = new GenericOrderedList([$value]);

        self::assertTrue($list->has(0));
    }

    public function testWontDetectNonExistingValue(): void
    {
        $list = new GenericOrderedList(['foo']);

        self::assertFalse($list->has(1));
    }

    /**
     * @return Generator<string,array{0:mixed}>
     */
    public static function listValues(): Generator
    {
        yield 'string' => ['foo'];
        yield 'empty string' => [''];
        yield 'non breaking space' => [' '];
        yield 'whitespace string' => [' '];
        yield 'tab' => ["\t"];
        yield 'integer' => [0];
        yield 'positive integer' => [PHP_INT_MAX];
        yield 'negative integer' => [PHP_INT_MIN];
        yield 'null' => [null];
        yield 'true' => [true];
        yield 'false' => [false];
        yield 'object' => [new stdClass()];
    }

    public function testForAllIsExecutedForAllEntries(): void
    {
        $list = new GenericOrderedList([
            'bar',
            'baz',
            'ooq',
        ]);

        $callable = new CallableObject(['bar', 0], ['baz', 1], ['ooq', 2]);
        $list->forAll($callable)->execute();
    }

    public function testWillGenerateErrorCollectionWhileExecutingForAll(): void
    {
        $list = new GenericOrderedList([
            'bar',
            'baz',
            'ooq',
        ]);

        $callable = function (string $value, int $index): void {
            $this->iteration++;

            if ($index === 1) {
                throw new RuntimeException((string) $index);
            }
        };

        $errorCollection = null;

        try {
            $list->forAll($callable)->execute();
        } catch (OrderedErrorCollection $errorCollection) {
        }

        self::assertEquals($list->count(), $this->iteration);
        self::assertInstanceOf(OrderedErrorCollection::class, $errorCollection);
        $errors = $errorCollection->errors();
        self::assertCount($list->count(), $errors);
        self::assertInstanceOf(RuntimeException::class, $errors->at(1));
    }

    public function testWillGenerateErrorCollectionWhileExecutingForAllButStopsExecutionOnError(): void
    {
        $list = new GenericOrderedList([
            'bar',
            'baz',
            'ooq',
        ]);

        $callable = function (string $value, int $index): void {
            $this->iteration++;
            if ($index === 1) {
                throw new RuntimeException((string) $index);
            }
        };

        $errorCollection = null;

        try {
            $list->forAll($callable)->stopOnError()->execute();
        } catch (OrderedErrorCollection $errorCollection) {
        }

        self::assertNotEquals($list->count(), $this->iteration);
        self::assertEquals(2, $this->iteration);
        self::assertInstanceOf(OrderedErrorCollection::class, $errorCollection);
        $errors = $errorCollection->errors();
        self::assertCount(2, $errors);
        self::assertNull($errors->at(0));
        self::assertInstanceOf(RuntimeException::class, $errors->at(1));
    }

    public function testForAllPromiseWillSuppressErrors(): void
    {
        $map = new GenericOrderedList(['bar']);

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

        $map = new GenericOrderedList(['bar']);

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

    public function testReverseWillProperlyReverseTheCollection(): void
    {
        $data     = range(0, 99);
        $expected = array_reverse($data);

        $list = new GenericOrderedList($data);
        self::assertSame($expected, $list->reverse()->toNativeArray());
    }

    #[DataProvider('joinableValues')]
    public function testCanJoin(string|Stringable $joinableValue): void
    {
        $list = new GenericOrderedList([$joinableValue]);

        self::assertEquals((string) $joinableValue, $list->join());
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
        $list = new GenericOrderedList([new stdClass()]);

        $this->expectException(RuntimeException::class);
        $list->join();
    }

    public function testWillJoinValuesWithSeperator(): void
    {
        $list = new GenericOrderedList([
            'foo',
            'bar',
            'baz',
        ]);

        self::assertSame('foo:bar:baz', $list->join(':'));
    }

    public function testWillFindIndexOfFirstMatchingItem(): void
    {
        $list = new GenericOrderedList([
            8,
            100,
            1000,
        ]);

        /** @psalm-suppress TypeDoesNotContainType Might be a psalm bug */
        self::assertSame(1, $list->findFirstMatchingIndex(fn (int $value) => $value % 10 === 0));
    }

    public function testWillReturnNullWhenNoItemMatchesFilter(): void
    {
        $list = new GenericOrderedList([
            2,
            4,
            6,
            8,
        ]);

        self::assertNull($list->findFirstMatchingIndex(fn (int $value) => $value % 2 !== 0));
    }

    public function testWillPrependValueToTheList(): void
    {
        /** @var OrderedListInterface<non-empty-string> $list */
        $list = new GenericOrderedList([
            'bar',
            'baz',
        ]);

        $list = $list->prepend('foo');
        self::assertSame(['foo', 'bar', 'baz'], $list->toNativeArray());
    }

    public function testWillReduceOrderedList(): void
    {
        $list = new GenericOrderedList([
            1,
            2,
            3,
            4,
            5,
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
        $list = new GenericOrderedList([]);
        self::assertSame(PHP_INT_MAX, $list->reduce(fn () => 1, PHP_INT_MAX));
    }

    public function testWillRemoveItemAtSpecificPosition(): void
    {
        $list = new GenericOrderedList([
            1,
            2,
            3,
        ]);

        $list = $list->removeAt(1);
        self::assertSame([1, 3], $list->toNativeArray());
    }
}
