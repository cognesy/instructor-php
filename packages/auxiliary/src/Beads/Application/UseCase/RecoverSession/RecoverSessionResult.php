<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\RecoverSession;

use Cognesy\Auxiliary\Beads\Domain\Collection\TaskCollection;

/**
 * Recover Session Result
 *
 * Result of recovering an agent's session.
 */
final readonly class RecoverSessionResult
{
    public function __construct(
        public bool $success,
        public ?TaskCollection $inProgressTasks = null,
        public ?string $error = null,
    ) {}

    public static function success(TaskCollection $inProgressTasks): self
    {
        return new self(true, $inProgressTasks, null);
    }

    public static function failure(string $error): self
    {
        return new self(false, null, $error);
    }

    public function hasActiveTasks(): bool
    {
        return $this->inProgressTasks !== null && ! $this->inProgressTasks->isEmpty();
    }
}
