<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Events\Event;

/**
 * Response models implementing this interface will be receiving events, so they can handle custom operations,
 * like logging, modifying the response, etc.
 */
interface CanReceiveEvents
{
    /**
     * Method called on each event that class implementing this interface can handle.
     */
    public function onEvent(Event $event) : void;

    /**
     * IDEA TO CONSIDER:
     * Returns an array of event classes that this response model is interested in.
     * If returned array is empty, the response model will receive all events.
     * @return array<class-string<Event>>
     */
    // public static function subscribeTo() : array;
}
