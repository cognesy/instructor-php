<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Runtime\TellDelegationScope;
use Cognesy\Tell\Runtime\TellDiagnostics;

interface CanBuildTellAgent
{
    public function definitions(string $projectPath): AgentDefinitionRegistry;

    public function definition(TellRequest $request): AgentDefinition;

    public function build(
        TellRequest $request,
        ?CanProvideCancellationSignal $cancellation = null,
        ?AgentDefinition $definition = null,
        ?TellDelegationScope $delegation = null,
        ?TellDiagnostics $diagnostics = null,
    ): AgentLoop;

    public function assertReady(TellRequest $request): void;

}
