<?php

declare(strict_types=1);

namespace Cognesy\Tell\Resource;

use Closure;
use Cognesy\Tell\Contracts\CanObserveTellResources;

final readonly class TellResourceObservers implements CanObserveTellResources
{
    /** @param Closure(TellResourceEvent): void $listener */
    private function __construct(private Closure $listener) {}

    /** @param callable(TellResourceEvent): void $listener */
    public static function callback(callable $listener): self
    {
        return new self(Closure::fromCallable($listener));
    }

    public function observe(TellResourceEvent $event): void
    {
        ($this->listener)($event);
    }
}
