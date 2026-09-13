<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Execution;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Configuration\CanResolveTellConfiguration;
use Cognesy\Tell\Core\Contract\Observation\CanJournalTellExecution;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellExecutionWorkspace;

/** Creates an isolated execution engine for each Tell run. */
final readonly class TellRuntimeFactory
{
    public function __construct(
        private CanBuildTellAgent $agents,
        private CanOpenTellExecutionWorkspace $workspaces,
        private CanTraceTellExecution $tracer,
        private CanJournalTellExecution $journal,
        private CanProvideCancellationSignal $cancellation,
        private CanResolveTellConfiguration $configuration,
        private CanObserveTellExecution $observer,
    ) {}

    public function create(?CanProvideCancellationSignal $cancellation = null): TellRuntime {
        return new TellRuntime(
            agents: $this->agents,
            cancellation: $cancellation ?? $this->cancellation,
            configuration: $this->configuration,
            observer: $this->observer,
            workspaces: $this->workspaces,
            tracer: $this->tracer,
            journal: $this->journal,
        );
    }
}
