<?php

declare(strict_types=1);

namespace Cognesy\Logging\Contracts;

use Cognesy\Events\Event;
use Cognesy\Logging\LogContext;
use Cognesy\Logging\LogEntry;

/**
 * Formats events and context into log entries
 */
interface EventFormatter
{
    /**
     * Format an event and context into a log entry
     *
     * @param Event $event The source event
     * @param LogContext $context The enriched context
     * @return LogEntry The formatted log entry
     */
    public function __invoke(Event $event, LogContext $context): LogEntry;
}