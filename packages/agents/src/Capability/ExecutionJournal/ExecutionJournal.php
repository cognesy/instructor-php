<?php

declare(strict_types=1);

namespace Cognesy\Agents\Capability\ExecutionJournal;

interface ExecutionJournal
{
    public function append(ExecutionJournalRecord $record): void;

    /** @return list<ExecutionJournalRecord> */
    public function all(): array;
}
