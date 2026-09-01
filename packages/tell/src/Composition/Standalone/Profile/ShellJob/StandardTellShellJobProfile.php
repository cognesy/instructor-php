<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone\Profile\ShellJob;

use Cognesy\Tell\Capability\ShellJob\Process\NullTellShellJobObserver;
use Cognesy\Tell\Capability\ShellJob\Process\TellShellJobApprovals;
use Cognesy\Tell\Capability\ShellJob\Process\TellShellJobPolicy;
use Cognesy\Tell\Core\Contract\ShellJob\CanApproveTellShellJobs;
use Cognesy\Tell\Core\Contract\ShellJob\CanObserveTellShellJobs;

/** Opt-in composition profile for persistent shell jobs. */
final readonly class StandardTellShellJobProfile
{
    public static function builder(
        string $project,
        ?TellShellJobPolicy $policy = null,
        ?CanApproveTellShellJobs $approval = null,
        ?CanObserveTellShellJobs $observer = null,
    ): TellShellJobHostBuilder {
        return new TellShellJobHostBuilder(
            projectDirectory: $project,
            policy: $policy ?? new TellShellJobPolicy(),
            approval: $approval ?? TellShellJobApprovals::denyAll(),
            observer: $observer ?? new NullTellShellJobObserver(),
        );
    }
}
