<?php

declare(strict_types=1);

namespace Boesing\TypedArrays;

use RuntimeException;

/**
 * @internal
 */
final class ForAllPromise implements ForAllPromiseInterface
{
    /** @var callable():void|null */
    private $finally;

    /** @var callable */
    private $task;

    /** @var bool */
    private $suppressErrors = false;

    /** @var bool */
    private $executed = false;

    /**
     * @param callable():void $task
     */
    public function __construct(callable $task)
    {
        $this->task = $task;
    }

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
            ($this->task)();
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
}
