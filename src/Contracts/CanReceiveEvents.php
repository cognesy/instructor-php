<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Events\Event;

/**
 * Response models implementing this interface will be receiving events, so they can handle custom operations,
 * like logging, modifying the response, etc.
 */
interface CanReceiveEvents
{
    public function onEvent(Event $event) : void;
}
