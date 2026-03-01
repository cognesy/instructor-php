<?php

declare(strict_types=1);

namespace Cognesy\Logging\Formatters;

use Cognesy\Events\Event;
use Cognesy\Logging\Contracts\EventFormatter;
use Cognesy\Logging\LogContext;
use Cognesy\Logging\LogEntry;

/**
 * Formatter with custom message templates per event class
 */
final readonly class MessageTemplateFormatter implements EventFormatter
{
    public function __construct(
        private array $templates = [],
        private string $defaultTemplate = '{event_name}',
        private string $channel = 'instructor',
    ) {}

    public function __invoke(Event $event, LogContext $context): LogEntry
    {
        $template = $this->templates[$event::class] ?? $this->defaultTemplate;
        $message = $this->interpolateTemplate($template, $event, $context);

        return LogEntry::create(
            level: $event->logLevel,
            message: $message,
            context: $context->toArray(),
            timestamp: $context->eventTime,
            channel: $this->channel,
        );
    }

    private function interpolateTemplate(string $template, Event $event, LogContext $context): string
    {
        $eventName = $this->getEventName($event);

        // Build replacement map
        $replacements = [
            '{event_class}' => $context->eventClass,
            '{event_name}' => $eventName,
            '{event_id}' => $context->eventId,
        ];

        // Add event data replacements
        if (is_array($context->eventData)) {
            foreach ($context->eventData as $key => $value) {
                if (is_scalar($value) || is_null($value)) {
                    $placeholder = '{' . $key . '}';
                    if (array_key_exists($placeholder, $replacements)) {
                        continue;
                    }
                    $replacements[$placeholder] = (string) $value;
                }
            }
        }

        // Add framework context replacements
        foreach ($context->frameworkContext as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $replacements["{framework.{$key}}"] = (string) $value;
            }
        }

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    private function getEventName(Event $event): string
    {
        $className = $event::class;
        $parts = explode('\\', $className);
        return end($parts);
    }
}
