<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Agent;

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Agent\CanDescribeTellDelegation;
use Cognesy\Tell\Core\Contract\Agent\CanRecordTellAgentDiagnostics;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Data\TellRequest;

/** Request-local assembly passed to explicitly selected agent contributions. */
final readonly class TellAgentAssembly
{
    public function __construct(
        public TellRequest $request,
        public AgentDefinitionRegistry $definitions,
        public AgentCapabilityRegistry $capabilities,
        public ToolRegistry $tools,
        public CanBuildTellAgent $agents,
        public CanTraceTellExecution $tracer,
        public ?CanDescribeTellDelegation $delegation = null,
        public ?CanRecordTellAgentDiagnostics $diagnostics = null,
    ) {}
}
