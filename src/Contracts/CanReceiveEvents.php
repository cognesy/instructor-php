<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Events\Event;

interface CanReceiveEvents
{
    public function onEvent(Event $event) : void;
}