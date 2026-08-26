<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Contracts\CanResolveTellConfiguration;
use Cognesy\Tell\Contracts\CanObserveTellExecution;

/** @internal Static-composition bridge; applications use CanBuildTellAgent. */
interface CanOpenTellRuntime
{
    public function runtime(
        ?CanProvideCancellationSignal $cancellation = null,
        ?CanResolveTellConfiguration $configuration = null,
        ?CanObserveTellExecution $observer = null,
    ): TellRuntime;

    /** @internal Compatibility bridge while legacy workspace facades are migrated. */
    public function agents(): TellAgentFactory;
}
