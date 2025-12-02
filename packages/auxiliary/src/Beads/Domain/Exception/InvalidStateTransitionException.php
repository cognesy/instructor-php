<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Exception;

use Cognesy\Auxiliary\Beads\Domain\Model\TaskStatus;

/**
 * Invalid State Transition Exception
 *
 * Thrown when attempting an invalid task state transition.
 */
final class InvalidStateTransitionException extends BeadsException
{
    public function __construct(
        string $message,
        public readonly ?TaskStatus $from = null,
        public readonly ?TaskStatus $to = null,
    ) {
        parent::__construct($message);
    }

    public static function fromTo(TaskStatus $from, TaskStatus $to): self
    {
        return new self(
            "Cannot transition from {$from->value} to {$to->value}",
            $from,
            $to,
        );
    }
}
