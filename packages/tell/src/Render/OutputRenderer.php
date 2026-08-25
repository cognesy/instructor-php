<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Tell\Observability\TellEventNormalizer;

interface OutputRenderer
{
    public function attach(AgentLoop $loop, ?TellEventNormalizer $events = null): void;

    /** @param list<string> $warnings */
    /** @param array{name: string, source: 'current'|'invocation'}|null $branch */
    public function finish(AgentState $state, array $warnings = [], bool $transient = false, ?array $branch = null): void;
}
