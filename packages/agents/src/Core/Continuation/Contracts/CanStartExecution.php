<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Continuation\Contracts;

use DateTimeImmutable;

/**
 * Optional hook for criteria that need a per-execution start timestamp.
 */
interface CanStartExecution
{
    public function executionStarted(DateTimeImmutable $startedAt): void;
}

