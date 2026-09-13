<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Observation\ExecutionJournal;

use Cognesy\Agents\Capability\ExecutionJournal\ExecutionJournal;
use Cognesy\Agents\Capability\ExecutionJournal\ExecutionJournalRecord;
use Cognesy\Tell\Core\Contract\Observation\CanRecordTellJournal;
use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Data\TellPublication;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellTermination;
use Cognesy\Tell\Data\TellTraceReference;
use DateTimeImmutable;

/** Adds Tell publication and trace disposition to the Agents execution journal. */
final class TellExecutionJournalRun implements CanRecordTellJournal
{
    private bool $recorded = false;

    public function __construct(
        private readonly ExecutionJournal $journal,
        private readonly TellRequest $request,
    ) {}

    #[\Override]
    public function recordOutcome(
        TellTermination $termination,
        TellPublication $publication,
        TellTraceReference $trace,
        TellExecutionMode $requestedMode,
    ): void {
        if ($this->recorded) {
            return;
        }
        $this->recorded = true;
        $context = $this->contextFacts($termination->toArray()['context']);
        $error = $termination->errors->all()[0] ?? null;
        $this->journal->append(new ExecutionJournalRecord(
            kind: 'tell.outcome',
            executionId: $termination->executionId,
            agentId: $this->request->agent,
            occurredAt: new DateTimeImmutable(),
            status: $termination->status,
            stepCount: $termination->stepCount,
            reason: $termination->stopSignal?->reason->value,
            errorCount: $termination->errors->count(),
            usage: $termination->usage,
            facts: [
                'agent' => $this->request->agent,
                'requestedMode' => $requestedMode->value,
                'publication' => $publication->status->value,
                'workspace' => $publication->workspace,
                'branch' => $publication->branch,
                'session' => $publication->session,
                'baseHead' => $publication->baseHead,
                'publishedHead' => $publication->publishedHead,
                'publicationFailure' => $publication->failureCode,
                'source' => $termination->stopSignal?->source,
                'errorCode' => $error?->code,
                'errorCategory' => $error?->category,
                'errorPhase' => $error?->phase,
                ...$context,
                'traceStatus' => $trace->status->value,
                'traceStorage' => $trace->storageKind,
                'tracePath' => $trace->path,
            ],
        ));
    }

    /**
     * @param array<string, int|float|string|bool|null> $context
     * @return array<string, int|float|string|bool|null>
     */
    private function contextFacts(array $context): array {
        $facts = [];
        foreach ($context as $key => $value) {
            $facts['context.' . $key] = $value;
        }

        return $facts;
    }
}
