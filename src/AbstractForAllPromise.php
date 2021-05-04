<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use RuntimeException;
use Throwable;

/**
 * @template TKey of array-key
 * @template TValue
 * @template-implements ForAllPromiseInterface<TKey,TValue>
 * @psalm-internal Boesing\TypedArrays
 * @internal
 */
abstract class AbstractForAllPromise implements ForAllPromiseInterface
{
    /** @var callable():void|null */
    private $finally;

    /** @var bool */
    private $suppressErrors = false;

    /** @var bool */
    private $stopOnError = false;

    /** @var bool */
    private $executed = false;

    /** @var iterable<TKey,TValue> */
    private $iterable;

    /** @var callable(TValue,TKey):void */
    private $callback;

    /**
     * @param iterable<TKey,TValue>      $iterable
     * @param callable(TValue,TKey):void $callback
     */
    final public function __construct(iterable $iterable, callable $callback)
    {
        $this->iterable = $iterable;
        $this->callback = $callback;
    }

    /**
     * @param array<TKey,Throwable|null> $errors
     *
     * @return MappedErrorCollection|OrderedErrorCollection
     */
    abstract protected function createThrowableErrorCollection(array $errors);

    public function finally(callable $callback): ForAllPromiseInterface
    {
        $this->finally = $callback;

        return $this;
    }

    public function suppressErrors(): ForAllPromiseInterface
    {
        $this->suppressErrors = true;

        return $this;
    }

    public function __destruct()
    {
        if ($this->executed) {
            return;
        }

        $this->execute();
    }

    public function execute(): void
    {
        $exception = null;

        try {
            (function (): void {
                $error = false;
                /** @var array<TKey,Throwable|null> $errors */
                $errors = [];
                foreach ($this->iterable as $index => $value) {
                    $throwable = null;
                    try {
                        ($this->callback)($value, $index);
                    } catch (Throwable $throwable) {
                        $error = true;
                        if ($this->stopOnError) {
                            break;
                        }
                    } finally {
                        $errors[$index] = $throwable;
                    }

                    /**
                     * Unset is being used to tell psalm that the value of the variable is being used.
                     * Psalm does not properly evaluate the `finally` block so otherwise, the usage won't be tracked.
                     */
                    unset($throwable);
                }

                if (! $error) {
                    return;
                }

                throw $this->createThrowableErrorCollection($errors);
            })();
        } catch (RuntimeException $exception) {
        } finally {
            $this->executed = true;
        }

        if ($this->finally !== null) {
            ($this->finally)();
        }

        if ($exception === null || $this->suppressErrors === true) {
            return;
        }

        throw $exception;
    }

    public function stopOnError(): ForAllPromiseInterface
    {
        $this->stopOnError = true;

        return $this;
    }
}
