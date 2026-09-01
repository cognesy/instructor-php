<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Agent;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Tell\Data\TellRequest;

interface CanBuildTellAgent
{
    public function definitions(string $projectPath): AgentDefinitionRegistry;

    public function definition(TellRequest $request): AgentDefinition;

    public function build(
        TellRequest $request,
        ?CanProvideCancellationSignal $cancellation = null,
        ?AgentDefinition $definition = null,
        ?CanDescribeTellDelegation $delegation = null,
        ?CanRecordTellAgentDiagnostics $diagnostics = null,
    ): AgentLoop;

    public function assertReady(TellRequest $request): void;

}
