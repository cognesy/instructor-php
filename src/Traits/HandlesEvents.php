<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Events\EventDispatcher;

trait HandlesEvents
{
    protected EventDispatcher $events;

    public function withEventDispatcher(EventDispatcher $events): self {
        $this->events = $events;
        return $this;
    }

    protected function events() : EventDispatcher {
        return $this->events;
    }

    protected function emit(Event $event) : void {
        $this->events->dispatch($event);
    }
}