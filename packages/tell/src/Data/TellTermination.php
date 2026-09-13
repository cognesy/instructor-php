<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use LogicException;

/** Authoritative Tell projection of one terminal agent execution. */
final readonly class TellTermination
{
    public function __construct(
        public string $executionId,
        public ExecutionStatus $status,
        public ?StopSignal $stopSignal,
        public int $stepCount,
        public ?AgentStepType $lastStepType,
        public ?InferenceFinishReason $finishReason,
        public InferenceUsage $usage,
        public TellExecutionErrors $errors,
    ) {}

    public static function fromState(AgentState $state): self {
        $execution = $state->execution();
        $status = $state->status();
        if ($execution === null || $status === null) {
            throw new LogicException('Tell termination requires an execution state.');
        }

        return new self(
            executionId: $execution->executionId()->toString(),
            status: $status,
            stopSignal: $state->stopSignal(),
            stepCount: $state->stepCount(),
            lastStepType: $state->lastStepType(),
            finishReason: $state->lastStep()?->finishReason(),
            usage: $state->usage(),
            errors: TellExecutionErrors::fromErrors($state->errors()),
        );
    }

    public function isCompleted(): bool {
        return $this->status === ExecutionStatus::Completed;
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'executionId' => $this->executionId,
            'status' => $this->status->value,
            'reason' => $this->stopSignal?->reason->value,
            'source' => $this->stopSignal?->source,
            'context' => $this->safeContext(),
            'steps' => $this->stepCount,
            'lastStepType' => $this->lastStepType?->value,
            'finishReason' => $this->finishReason?->value,
            'usage' => $this->usage->toTokenCounts(),
            'errors' => $this->errors->toArray(),
        ];
    }

    /** @return array<string, int|float|string|bool|null> */
    private function safeContext(): array {
        $context = [];
        $signal = $this->stopSignal;
        if ($signal === null) {
            return $context;
        }
        foreach ($signal->context as $key => $value) {
            if (!is_string($key) || !is_int($value) && !is_float($value) && !is_bool($value) && !is_null($value)) {
                continue;
            }
            $context[$key] = $value;
        }

        return $context;
    }
}
