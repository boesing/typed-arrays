<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use PHPUnit\Framework\TestCase;

final class TypedArrayFactoryTest extends TestCase
{
    private TypedArrayFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new TypedArrayFactory();
    }

    public function testCanCreateMapInstance(): void
    {
        $map = $this->factory->createMap(['foo' => 'bar']);

        self::assertEquals(['foo' => 'bar'], $map->toNativeArray());
    }

    public function testCanCreateOrderedListInstance(): void
    {
        $list = $this->factory->createOrderedList(['foo', 'bar']);
        self::assertEquals(['foo', 'bar'], $list->toNativeArray());
    }
}
