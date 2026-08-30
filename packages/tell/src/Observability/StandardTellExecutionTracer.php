<?php

declare(strict_types=1);

namespace Cognesy\Tell\Observability;

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Configuration\TellConfig;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Contracts\CanTraceTellExecution;
use Cognesy\Tell\Data\TellRequest;

final readonly class StandardTellExecutionTracer implements CanTraceTellExecution
{
    public function __construct(private TellPaths $paths) {}

    #[\Override]
    public function attach(AgentLoop $loop, TellRequest $request): void {
        (new ExecutionTraceWriter(
            $this->paths,
            TellConfig::fromFile($this->paths->configFile),
            $request->toOptions(),
        ))->attach($loop);
    }
}
