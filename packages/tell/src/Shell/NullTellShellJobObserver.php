<?php

declare(strict_types=1);

namespace Cognesy\Tell\Shell;

use Cognesy\Tell\Contracts\CanObserveTellShellJobs;
use Cognesy\Tell\Data\TellShellJobEvent;

final readonly class NullTellShellJobObserver implements CanObserveTellShellJobs
{
    public function observe(TellShellJobEvent $event): void {}
}
