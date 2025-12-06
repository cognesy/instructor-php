<?php

declare(strict_types=1);

namespace Cognesy\Logging\Contracts;

use Cognesy\Events\Event;

/**
 * Filters events to determine if they should be logged
 */
interface EventFilter
{
    /**
     * Determine if an event should pass through the pipeline
     *
     * @param Event $event The event to filter
     * @return bool True if event should be logged, false to skip
     */
    public function __invoke(Event $event): bool;
}