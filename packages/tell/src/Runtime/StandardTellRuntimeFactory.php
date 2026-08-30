<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Contracts\CanBuildTellAgent;
use Cognesy\Tell\Contracts\CanCreateTellRuntime;
use Cognesy\Tell\Contracts\CanObserveTellExecution;
use Cognesy\Tell\Contracts\CanResolveTellConfiguration;
use Cognesy\Tell\Contracts\CanTraceTellExecution;
use Cognesy\Tell\Workspace\WorkspaceRepository;

final readonly class StandardTellRuntimeFactory implements CanCreateTellRuntime
{
    public function __construct(
        private CanBuildTellAgent $agents,
        private WorkspaceRepository $workspaces,
        private TellPaths $paths,
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
            paths: $this->paths,
            tracer: $this->tracer,
        );
    }
}
