<?php

namespace Cognesy\Utils\Events\Extras;

use Cognesy\Utils\Events\Contracts\CanRegisterEventListeners;
use Psr\EventDispatcher\EventDispatcherInterface;

final class PsrEventDispatcherAdapter implements EventDispatcherInterface, CanRegisterEventListeners
{
    public function __construct(
        private readonly EventDispatcherInterface $inner
    ) {}

    private array $listeners = [];
    private array $wiretaps  = [];

    public function addListener(string $name, callable $listener): static
    {
        $this->listeners[$name][] = $listener;
        return $this;
    }

    public function wiretap(callable $listener): static
    {
        $this->wiretaps[] = $listener;
        return $this;
    }

    public function dispatch(object $event): object
    {
        // local listeners first
        foreach ($this->listeners as $class => $ls) {
            if ($event instanceof $class) {
                foreach ($ls as $l) {
                    $l($event);
                }
            }
        }

        // delegate to real dispatcher
        $event = $this->inner->dispatch($event);

        // taps see everything
        foreach ($this->wiretaps as $tap) {
            $tap($event);
        }

        return $event;
    }
}
