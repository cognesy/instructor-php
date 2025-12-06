<?php

declare(strict_types=1);

namespace Cognesy\Logging\Contracts;

use Cognesy\Events\Event;
use Cognesy\Logging\LogContext;

/**
 * Enriches events with additional context data
 */
interface EventEnricher
{
    /**
     * Enrich an event with additional context
     *
     * @param Event $event The event to enrich
     * @return LogContext Enriched context data
     */
    public function __invoke(Event $event): LogContext;
}