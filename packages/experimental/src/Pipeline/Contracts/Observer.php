<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline\Contracts;

/**
 * Provides hooks to observe the execution for monitoring or debugging.
 * Observers are read-only and must not mutate the payload or state.
 */
interface Observer
{
    public function beforeHandle(Operator $operator, mixed $payload): void;

    public function afterHandle(Operator $operator, mixed $result): void;
}