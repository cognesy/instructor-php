<?php

declare(strict_types=1);

namespace Cognesy\Logging;

use Cognesy\Events\Event;
use DateTimeImmutable;
use JsonSerializable;

/**
 * Immutable context data for logging
 */
readonly class LogContext implements JsonSerializable
{
    public function __construct(
        public string $eventId,
        public string $eventClass,
        public array $eventData,
        public DateTimeImmutable $eventTime,
        public array $frameworkContext = [],
        public array $performanceMetrics = [],
        public array $userContext = [],
    ) {}

    public static function fromEvent(Event $event, array $additionalContext = []): self
    {
        return new self(
            eventId: $event->id,
            eventClass: $event::class,
            eventData: is_array($event->data) ? $event->data : ['data' => $event->data],
            eventTime: $event->createdAt,
            frameworkContext: $additionalContext['framework'] ?? [],
            performanceMetrics: $additionalContext['metrics'] ?? [],
            userContext: $additionalContext['user'] ?? [],
        );
    }

    public function withFrameworkContext(array $context): self
    {
        return new self(
            eventId: $this->eventId,
            eventClass: $this->eventClass,
            eventData: $this->eventData,
            eventTime: $this->eventTime,
            frameworkContext: array_merge($this->frameworkContext, $context),
            performanceMetrics: $this->performanceMetrics,
            userContext: $this->userContext,
        );
    }

    public function withPerformanceMetrics(array $metrics): self
    {
        return new self(
            eventId: $this->eventId,
            eventClass: $this->eventClass,
            eventData: $this->eventData,
            eventTime: $this->eventTime,
            frameworkContext: $this->frameworkContext,
            performanceMetrics: array_merge($this->performanceMetrics, $metrics),
            userContext: $this->userContext,
        );
    }

    public function withUserContext(array $context): self
    {
        return new self(
            eventId: $this->eventId,
            eventClass: $this->eventClass,
            eventData: $this->eventData,
            eventTime: $this->eventTime,
            frameworkContext: $this->frameworkContext,
            performanceMetrics: $this->performanceMetrics,
            userContext: array_merge($this->userContext, $context),
        );
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_class' => $this->eventClass,
            'event_data' => $this->eventData,
            'event_time' => $this->eventTime->format(\DateTime::ISO8601),
            'framework' => $this->frameworkContext,
            'metrics' => $this->performanceMetrics,
            'user' => $this->userContext,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}