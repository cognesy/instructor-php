<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Tell\Contracts\CanBuildTellAgent;
use Cognesy\Tell\Contracts\CanObserveTellExecution;
use Cognesy\Tell\Contracts\CanResolveTellConfiguration;
use Cognesy\Tell\Data\TellRequest;

final readonly class StandardTellAgentBuilder implements CanBuildTellAgent, CanOpenTellRuntime
{
    public function __construct(private TellAgentFactory $agents) {}

    public function definition(TellRequest $request): AgentDefinition {
        return $this->agents->definition($request->toOptions());
    }

    public function build(TellRequest $request, ?CanProvideCancellationSignal $cancellation = null): AgentLoop {
        return $this->agents->build($request->toOptions(), cancellation: $cancellation);
    }

    public function runtime(
        ?CanProvideCancellationSignal $cancellation = null,
        ?CanResolveTellConfiguration $configuration = null,
        ?CanObserveTellExecution $observer = null,
    ): TellRuntime {
        return new TellRuntime($this->agents, $cancellation, $configuration, $observer);
    }

    public function agents(): TellAgentFactory {
        return $this->agents;
    }
}
