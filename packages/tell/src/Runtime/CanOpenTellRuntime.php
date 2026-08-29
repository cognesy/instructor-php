<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Contracts\CanObserveTellExecution;
use Cognesy\Tell\Contracts\CanResolveTellConfiguration;

/** @internal Static-composition bridge; applications use CanBuildTellAgent. */
interface CanOpenTellRuntime
{
    public function runtime(
        ?CanProvideCancellationSignal $cancellation = null,
        ?CanResolveTellConfiguration $configuration = null,
        ?CanObserveTellExecution $observer = null,
    ): TellRuntime;

    /** @internal Composition access for workspace-facing modules. */
    public function agents(): TellAgentFactory;
}
