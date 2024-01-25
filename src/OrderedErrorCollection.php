<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use RuntimeException;
use Throwable;
use Webmozart\Assert\Assert;

final class OrderedErrorCollection extends RuntimeException
{
    /**
     * @param OrderedListInterface<Throwable|null> $errors
     */
    private function __construct(private OrderedListInterface $errors)
    {
        Assert::false($errors->isEmpty(), 'Provided errors must not be empty!');

        parent::__construct('There were runtime errors while executing multiple tasks.');
    }

    /**
     * @param OrderedListInterface<Throwable|null> $errors
     */
    public static function create(OrderedListInterface $errors): self
    {
        return new self($errors);
    }

    /**
     * @return OrderedListInterface<Throwable|null>
     */
    public function errors(): OrderedListInterface
    {
        return $this->errors;
    }
}
