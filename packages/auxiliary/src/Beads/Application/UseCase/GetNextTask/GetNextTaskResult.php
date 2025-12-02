<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\GetNextTask;

use Cognesy\Auxiliary\Beads\Domain\Collection\TaskCollection;

/**
 * Get Next Task Result
 *
 * Result of getting next available task(s).
 */
final readonly class GetNextTaskResult
{
    public function __construct(
        public bool $success,
        public ?TaskCollection $tasks = null,
        public ?string $error = null,
    ) {}

    public static function success(TaskCollection $tasks): self
    {
        return new self(true, $tasks, null);
    }

    public static function failure(string $error): self
    {
        return new self(false, null, $error);
    }

    public function isEmpty(): bool
    {
        return $this->tasks === null || $this->tasks->isEmpty();
    }
}
