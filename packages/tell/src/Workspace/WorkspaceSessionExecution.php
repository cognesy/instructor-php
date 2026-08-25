<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\Data\AgentState;

final readonly class WorkspaceSessionExecution
{
    /** @param list<string> $warnings */
    public function __construct(
        public AgentState $state,
        public array $warnings = [],
        public bool $transient = false,
    ) {}
}
