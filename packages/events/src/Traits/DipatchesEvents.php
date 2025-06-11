<?php

namespace Cognesy\Events\Traits;

use Cognesy\Events\Contracts\CanHandleEvents;
use Psr\EventDispatcher\EventDispatcherInterface;

trait DipatchesEvents
{
    protected CanHandleEvents|EventDispatcherInterface $events;

    protected function dispatch(object $event): object {
        if (!isset($this->events)) {
            throw new \LogicException('Event dispatcher is not set. Please initialize it before dispatching events.');
        }
        return $this->events->dispatch($event);
    }
}