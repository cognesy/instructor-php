<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Observation\FilesystemTrace;

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Core\Configuration\TellConfig;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Data\TellRequest;

final readonly class StandardTellExecutionTracer implements CanTraceTellExecution
{
    public function __construct(private TellPaths $paths) {}

    #[\Override]
    public function attach(AgentLoop $loop, TellRequest $request): void {
        (new ExecutionTraceWriter(
            $this->paths,
            TellConfig::fromFile($this->paths->configFile),
            $request,
        ))->attach($loop);
    }
}
