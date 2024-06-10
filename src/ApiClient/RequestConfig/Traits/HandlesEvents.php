<?php

namespace Cognesy\Instructor\ApiClient\RequestConfig\Traits;

use Cognesy\Instructor\Events\EventDispatcher;

trait HandlesEvents
{
    private EventDispatcher $events;

    public function events() : EventDispatcher {
        return $this->events;
    }

    private function withEvents(?EventDispatcher $events) : static {
        $this->events = $events;
        return $this;
    }
}