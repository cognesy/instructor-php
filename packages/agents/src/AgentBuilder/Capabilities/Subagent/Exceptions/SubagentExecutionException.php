<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Subagent\Exceptions;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Enums\ExecutionStatus;
use Cognesy\Agents\Exceptions\AgentException;
use Cognesy\Polyglot\Inference\Data\Usage;

final class SubagentExecutionException extends AgentException
{
    public function __construct(
        public readonly string $subagentName,
        public readonly string $subagentId,
        public readonly ExecutionStatus $status,
        public readonly ?Usage $usage,
        public readonly int $steps,
        string $errorMessage,
    ) {
        parent::__construct(
            "Subagent '{$this->subagentName}' failed: {$errorMessage}"
        );
    }

    public static function fromState(AgentState $state, string $name): self {
        $errorMsg = $state->currentStepOrLast()?->errorsAsString() ?? 'Unknown error';

        return new self(
            subagentName: $name,
            subagentId: $state->agentId(),
            status: $state->status(),
            usage: $state->usage(),
            steps: $state->stepCount(),
            errorMessage: $errorMsg,
        );
    }
}
