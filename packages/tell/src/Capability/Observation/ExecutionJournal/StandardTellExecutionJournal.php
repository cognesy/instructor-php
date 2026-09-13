<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Observation\ExecutionJournal;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\ExecutionJournal\ExecutionJournalObserver;
use Cognesy\Tell\Core\Contract\Observation\CanJournalTellExecution;
use Cognesy\Tell\Core\Contract\Observation\CanRecordTellJournal;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Data\TellRequest;

final readonly class StandardTellExecutionJournal implements CanJournalTellExecution
{
    public function __construct(private TellPaths $paths) {}

    #[\Override]
    public function attach(AgentLoop $loop, TellRequest $request): CanRecordTellJournal {
        $journal = new FilesystemExecutionJournal($this->paths->executionJournal);
        (new ExecutionJournalObserver($journal))->attach($loop);

        return new TellExecutionJournalRun($journal, $request);
    }
}
