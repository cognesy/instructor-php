<?php

declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\ExecutionJournal;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\ExecutionJournal\ExecutionJournal;
use Cognesy\Agents\Capability\ExecutionJournal\ExecutionJournalObserver;
use Cognesy\Agents\Capability\ExecutionJournal\ExecutionJournalRecord;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;

it('records payload-free semantic execution transitions', function (): void {
    $journal = new class implements ExecutionJournal {
        /** @var list<ExecutionJournalRecord> */
        public array $records = [];

        public function append(ExecutionJournalRecord $record): void {
            $this->records[] = $record;
        }

        public function all(): array {
            return $this->records;
        }
    };
    $loop = AgentLoop::default()->withDriver(FakeAgentDriver::fromResponses('private answer'));
    (new ExecutionJournalObserver($journal))->attach($loop);

    $loop->execute(AgentState::empty()->withUserMessage('private prompt'));
    $kinds = array_map(
        static fn (ExecutionJournalRecord $record): string => $record->kind,
        $journal->all(),
    );

    expect($kinds)->toContain('execution.started', 'step.completed', 'execution.settled')
        ->not->toContain('execution.stop_observed')
        ->and(array_count_values($kinds)['execution.settled'])->toBe(1)
        ->and(json_encode(array_map(
            static fn (ExecutionJournalRecord $record): array => $record->toArray(),
            $journal->all(),
        ), JSON_THROW_ON_ERROR))->not->toContain('private prompt')
        ->not->toContain('private answer');
});
