<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use RuntimeException;
use Throwable;
use Webmozart\Assert\Assert;

final class MappedErrorCollection extends RuntimeException
{
    /** @var MapInterface<string,Throwable> */
    private $errors;

    /**
     * @param MapInterface<string,Throwable> $errors
     */
    private function __construct(MapInterface $errors)
    {
        Assert::false($errors->isEmpty(), 'Provided errors must not be empty!');
        $this->errors = $errors;
        parent::__construct('There were runtime errors while executing multiple tasks.');
    }

    /**
     * @param MapInterface<string,Throwable> $errors
     */
    public static function create(MapInterface $errors): self
    {
        return new self($errors);
    }

    /**
     * @return MapInterface<string,Throwable>
     */
    public function errors(): MapInterface
    {
        return $this->errors;
    }
}
