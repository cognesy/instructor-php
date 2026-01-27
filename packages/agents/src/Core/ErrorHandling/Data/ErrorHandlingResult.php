<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\ErrorHandling\Data;

use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\Exceptions\AgentException;

/**
 * Immutable outcome of error handling.
 *
 * Timing is owned by the orchestrator (Agent), not the error handler.
 */
final readonly class ErrorHandlingResult
{
    public function __construct(
        public AgentStep $failureStep,
        public ContinuationOutcome $outcome,
        public AgentStatus $finalStatus,
        public AgentException $exception,
    ) {}
}

