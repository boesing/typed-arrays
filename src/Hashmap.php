<?php
declare(strict_types=1);

namespace Boesing\TypedArrays;

use Webmozart\Assert\Assert;

/**
 * @template            TValue
 * @template-extends    Map<string,TValue>
 * @template-implements HashmapInterface<TValue>
 */
abstract class Hashmap extends Map implements HashmapInterface
{
    /**
     * @psalm-param array<string,TValue> $data
     */
    public function __construct(array $data)
    {
        Assert::isMap($data);
        parent::__construct($data);
    }

    public function remove($element): MapInterface
    {
        /** @psalm-suppress MissingClosureParamType */
        return $this->filter(static function ($value) use ($element): bool {
            return $value !== $element;
        });
    }

    public function map(callable $callback): MapInterface
    {
        return new GenericHashmap(array_map($callback, $this->data));
    }
}
