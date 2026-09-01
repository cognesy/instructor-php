<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone\Profile;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Execution\CanCreateTellRuntime;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Core\Contract\Configuration\CanResolveTellConfiguration;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellExecutionWorkspace;
use Cognesy\Tell\Core\Execution\TellRuntime;

final readonly class StandardTellRuntimeFactory implements CanCreateTellRuntime
{
    public function __construct(
        private CanBuildTellAgent $agents,
        private CanOpenTellExecutionWorkspace $workspaces,
        private CanTraceTellExecution $tracer,
        private CanProvideCancellationSignal $cancellation,
        private CanResolveTellConfiguration $configuration,
        private CanObserveTellExecution $observer,
    ) {}

    #[\Override]
    public function create(?CanProvideCancellationSignal $cancellation = null): TellRuntime {
        return new TellRuntime(
            agents: $this->agents,
            cancellation: $cancellation ?? $this->cancellation,
            configuration: $this->configuration,
            observer: $this->observer,
            workspaces: $this->workspaces,
            tracer: $this->tracer,
        );
    }
}
