<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

/**
 * @template         TKey of string
 * @template         TValue
 * @template-extends Map<TKey,TValue>
 * @psalm-immutable
 */
final class GenericMap extends Map
{
}
