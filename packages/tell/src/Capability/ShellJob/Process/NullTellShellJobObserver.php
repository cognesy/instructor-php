<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\ShellJob\Process;

use Cognesy\Tell\Core\Contract\ShellJob\CanObserveTellShellJobs;
use Cognesy\Tell\Data\TellShellJobEvent;

final readonly class NullTellShellJobObserver implements CanObserveTellShellJobs
{
    #[\Override]
    public function observe(TellShellJobEvent $event): void {}
}
