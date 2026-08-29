<?php

declare(strict_types=1);

namespace Cognesy\Tell\Shell;

use Closure;
use Cognesy\Tell\Contracts\CanObserveTellShellJobs;
use Cognesy\Tell\Data\TellShellJobEvent;

final readonly class TellShellJobObservers implements CanObserveTellShellJobs
{
    /** @param Closure(TellShellJobEvent): void $listener */
    private function __construct(private Closure $listener) {}

    /** @param callable(TellShellJobEvent): void $listener */
    public static function callback(callable $listener): self {
        return new self(Closure::fromCallable($listener));
    }

    public function observe(TellShellJobEvent $event): void {
        ($this->listener)($event);
    }
}
