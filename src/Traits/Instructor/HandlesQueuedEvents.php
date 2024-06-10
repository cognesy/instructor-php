<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Events\Event;

trait HandlesQueuedEvents
{
    protected $queuedEvents = [];

    protected function queueEvent(Event $event) : static {
        $this->queuedEvents[] = $event;
        return $this;
    }

    /**
     * Dispatches all events queued before $events was initialized
     */
    protected function dispatchQueuedEvents() : void {
        foreach ($this->queuedEvents as $event) {
            $this->events->dispatch($event);
        }
        $this->queuedEvents = [];
    }
}