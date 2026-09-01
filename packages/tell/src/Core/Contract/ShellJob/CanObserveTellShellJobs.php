<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\ShellJob;

use Cognesy\Tell\Data\TellShellJobEvent;

interface CanObserveTellShellJobs
{
    public function observe(TellShellJobEvent $event): void;
}
