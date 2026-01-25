<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\ErrorHandling;

use Cognesy\Agents\Agent\Continuation\ContinuationOutcome;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\Enums\AgentStatus;
use Cognesy\Agents\Agent\Exceptions\AgentException;

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

