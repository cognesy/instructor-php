<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\ShellJob\Process;

use Closure;
use Cognesy\Tell\Core\Contract\ShellJob\CanObserveTellShellJobs;
use Cognesy\Tell\Data\TellShellJobEvent;

final readonly class TellShellJobObservers implements CanObserveTellShellJobs
{
    /** @param Closure(TellShellJobEvent): void $listener */
    private function __construct(private Closure $listener) {}

    /** @param callable(TellShellJobEvent): void $listener */
    public static function callback(callable $listener): self {
        return new self(Closure::fromCallable($listener));
    }

    #[\Override]
    public function observe(TellShellJobEvent $event): void {
        ($this->listener)($event);
    }
}
