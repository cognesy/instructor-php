<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Utils\Events\Event;

/**
 * Response models implementing this interface will be receiving events,
 * so they can handle custom operations, like logging, modifying the response, etc.
 */
interface CanReceiveEvents
{
    /**
     * Method called on each event that class implementing this interface can handle.
     *
     * @param Event $event The event to be handled
     */
    public function onEvent(Event $event) : void;
}
