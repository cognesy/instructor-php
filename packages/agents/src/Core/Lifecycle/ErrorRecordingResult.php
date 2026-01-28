<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Exceptions\AgentException;

/**
 * Result of recording an error step.
 */
final readonly class ErrorRecordingResult
{
    public function __construct(
        public AgentState $state,
        public AgentException $exception,
        public bool $isFailed,
    ) {}
}

