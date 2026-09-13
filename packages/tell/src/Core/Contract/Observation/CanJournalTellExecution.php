<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Observation;

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Data\TellRequest;

interface CanJournalTellExecution
{
    public function attach(AgentLoop $loop, TellRequest $request): CanRecordTellJournal;
}
