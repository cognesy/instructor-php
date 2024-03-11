<?php

namespace Tests\Examples\Instructor;

use Cognesy\Instructor\Events\Event;

class EventSink
{
    protected array $events = [];

    public function getEvents() : array {
        return $this->events;
    }

    public function count() : int {
        return count($this->events);
    }

    public function onEvent(Event $e) {
        $this->events[] = $e;
    }

    public function first() : Event {
        return $this->events[0];
    }
}