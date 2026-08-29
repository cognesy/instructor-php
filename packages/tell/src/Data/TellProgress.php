<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;

/** A completed agent-loop checkpoint yielded by Tell::runStream(). */
final readonly class TellProgress
{
    public function __construct(private AgentState $state) {}

    public function stepCount(): int {
        return $this->state->steps()->count();
    }

    public function status(): ?ExecutionStatus {
        return $this->state->status();
    }

    public function usage(): InferenceUsage {
        return $this->state->usage();
    }

    public function hasToolCalls(): bool {
        return $this->state->lastStep()?->hasToolCalls() ?? false;
    }

    public function isCompleted(): bool {
        return $this->state->status() === ExecutionStatus::Completed;
    }
}
