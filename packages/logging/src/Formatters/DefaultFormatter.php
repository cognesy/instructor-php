<?php

declare(strict_types=1);

namespace Cognesy\Logging\Formatters;

use Cognesy\Events\Event;
use Cognesy\Logging\Contracts\EventFormatter;
use Cognesy\Logging\LogContext;
use Cognesy\Logging\LogEntry;

/**
 * Default event formatter
 */
final readonly class DefaultFormatter implements EventFormatter
{
    public function __construct(
        private string $messageTemplate = '{event_class}: {message}',
        private string $channel = 'instructor',
    ) {}

    public function __invoke(Event $event, LogContext $context): LogEntry
    {
        $message = $this->formatMessage($event, $context);

        return LogEntry::create(
            level: $event->logLevel,
            message: $message,
            context: $context->toArray(),
            timestamp: $context->eventTime,
            channel: $this->channel,
        );
    }

    private function formatMessage(Event $event, LogContext $context): string
    {
        $eventName = $this->getEventName($event);

        $placeholders = [
            '{event_class}' => $context->eventClass,
            '{event_name}' => $eventName,
            '{message}' => $eventName,
            '{event_id}' => $context->eventId,
        ];

        return str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $this->messageTemplate
        );
    }

    private function getEventName(Event $event): string
    {
        $className = $event::class;
        $parts = explode('\\', $className);
        return end($parts);
    }
}