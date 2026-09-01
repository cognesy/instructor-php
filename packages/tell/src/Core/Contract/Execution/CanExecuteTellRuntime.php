<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Execution;

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellResult;
use Generator;

/** Per-run execution boundary used by delivery and direct-tool adapters. */
interface CanExecuteTellRuntime extends CanRunTell
{
    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    #[\Override]
    public function run(TellRequest $request, ?callable $prepareLoop = null): TellResult;

    /**
     * @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop
     * @return Generator<int, TellProgress, mixed, TellResult>
     */
    #[\Override]
    public function stream(TellRequest $request, ?callable $prepareLoop = null): Generator;

    /** @param callable(AgentLoop, TellRequest, ?string): void|null $prepareLoop */
    #[\Override]
    public function start(TellRequest $request, ?callable $prepareLoop = null): CanObserveTellRun;

    public function resolveDirectRequest(TellRequest $request): TellRequest;
}
