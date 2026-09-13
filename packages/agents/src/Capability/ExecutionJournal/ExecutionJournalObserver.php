<?php

declare(strict_types=1);

namespace Cognesy\Agents\Capability\ExecutionJournal;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Events\AgentExecutionAbandoned;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionFailed;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentExecutionStopped;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;

/** Persists semantic lifecycle transitions from typed AgentLoop events. */
final readonly class ExecutionJournalObserver
{
    public function __construct(private ExecutionJournal $journal) {}

    public function attach(AgentLoop $loop): void {
        $loop->wiretap(function (object $event): void {
            $record = $this->record($event);
            if ($record !== null) {
                $this->journal->append($record);
            }
        });
    }

    private function record(object $event): ?ExecutionJournalRecord {
        return match (true) {
            $event instanceof AgentExecutionStarted => new ExecutionJournalRecord(
                kind: 'execution.started',
                executionId: $event->executionId,
                agentId: $event->agentId,
                occurredAt: $event->startedAt,
                status: ExecutionStatus::InProgress,
            ),
            $event instanceof AgentStepCompleted => new ExecutionJournalRecord(
                kind: 'step.completed',
                executionId: $event->executionId,
                agentId: $event->agentId,
                occurredAt: $event->completedAt,
                status: ExecutionStatus::InProgress,
                stepCount: $event->stepNumber,
                errorCount: $event->errorCount,
                usage: $event->usage,
            ),
            $event instanceof AgentExecutionStopped => new ExecutionJournalRecord(
                kind: 'execution.stop_observed',
                executionId: $event->executionId,
                agentId: $event->agentId,
                occurredAt: $event->stoppedAt,
                status: ExecutionStatus::Stopped,
                stepCount: $event->totalSteps,
                reason: $event->stopReason->value,
                facts: [
                    'source' => $event->source,
                    ...$this->contextFacts($event->stopContext),
                ],
            ),
            $event instanceof AgentExecutionFailed => new ExecutionJournalRecord(
                kind: 'execution.failure_observed',
                executionId: $event->executionId,
                agentId: $event->agentId,
                occurredAt: $event->failedAt,
                status: ExecutionStatus::Failed,
                stepCount: $event->stepsCompleted,
                errorCount: 1,
                usage: $event->totalUsage,
            ),
            $event instanceof AgentExecutionCompleted => new ExecutionJournalRecord(
                kind: 'execution.settled',
                executionId: $event->executionId,
                agentId: $event->agentId,
                occurredAt: $event->completedAt,
                status: $event->status,
                stepCount: $event->totalSteps,
                errorCount: $event->status === ExecutionStatus::Failed ? 1 : 0,
                usage: $event->totalUsage,
            ),
            $event instanceof AgentExecutionAbandoned => new ExecutionJournalRecord(
                kind: 'execution.abandoned',
                executionId: $event->executionId,
                agentId: $event->agentId,
                occurredAt: $event->abandonedAt,
                status: $event->status,
                stepCount: $event->totalSteps,
                usage: InferenceUsage::none(),
            ),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, int|float|string|bool|null>
     */
    private function contextFacts(array $context): array {
        $facts = [];
        foreach ($context as $key => $value) {
            if (!is_string($key) || !is_int($value) && !is_float($value) && !is_bool($value) && !is_null($value)) {
                continue;
            }
            $facts['context.' . $key] = $value;
        }

        return $facts;
    }
}
