<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Tell\Contracts\CanBuildTellAgent;
use Cognesy\Tell\Data\TellRequest;

final readonly class StandardTellAgentBuilder implements CanBuildTellAgent
{
    public function __construct(private TellAgentFactory $agents) {}

    #[\Override]
    public function definitions(string $projectPath): AgentDefinitionRegistry {
        return $this->agents->definitions($projectPath);
    }

    #[\Override]
    public function definition(TellRequest $request): AgentDefinition {
        return $this->agents->definition($request->toOptions());
    }

    #[\Override]
    public function build(
        TellRequest $request,
        ?CanProvideCancellationSignal $cancellation = null,
        ?AgentDefinition $definition = null,
        ?TellDelegationScope $delegation = null,
        ?TellDiagnostics $diagnostics = null,
    ): AgentLoop {
        return $this->agents->build(
            $request->toOptions(),
            $definition,
            $cancellation,
            $delegation,
            $diagnostics,
        );
    }

    #[\Override]
    public function assertReady(TellRequest $request): void {
        $this->agents->assertReady($request->toOptions());
    }

}
