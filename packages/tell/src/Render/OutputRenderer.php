<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;

interface OutputRenderer
{
    public function attach(AgentLoop $loop): void;

    /** @param list<string> $warnings */
    public function finish(AgentState $state, array $warnings = [], bool $transient = false): void;
}
