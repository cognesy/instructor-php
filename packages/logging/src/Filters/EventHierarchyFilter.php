<?php

declare(strict_types=1);

namespace Cognesy\Logging\Filters;

use Cognesy\Events\Event;
use Cognesy\Logging\Contracts\EventFilter;

/**
 * Filters events by class hierarchy (includes parent classes)
 */
final readonly class EventHierarchyFilter implements EventFilter
{
    public function __construct(
        private array $excludedClasses = [],
        private array $includedClasses = [],
    ) {}

    public function __invoke(Event $event): bool
    {
        $eventClass = $event::class;
        $eventHierarchy = $this->getClassHierarchy($eventClass);

        // If included classes specified, check if any parent class is included
        if (!empty($this->includedClasses)) {
            return !empty(array_intersect($eventHierarchy, $this->includedClasses));
        }

        // Otherwise, exclude if any parent class is excluded
        return empty(array_intersect($eventHierarchy, $this->excludedClasses));
    }

    private function getClassHierarchy(string $className): array
    {
        $hierarchy = [$className];

        $current = $className;
        while (($parent = get_parent_class($current)) !== false) {
            $hierarchy[] = $parent;
            $current = $parent;
        }

        return $hierarchy;
    }

    /**
     * Create filter that includes only HTTP-related events
     */
    public static function httpEventsOnly(): self
    {
        return new self(
            includedClasses: [
                \Cognesy\HttpClient\Events\HttpEvent::class,
            ]
        );
    }

    /**
     * Create filter that includes only StructuredOutput events
     */
    public static function structuredOutputEventsOnly(): self
    {
        return new self(
            includedClasses: [
                \Cognesy\Instructor\Events\StructuredOutputEvent::class,
            ]
        );
    }

    /**
     * Create filter that excludes debug HTTP events
     */
    public static function excludeHttpDebug(): self
    {
        return new self(
            excludedClasses: [
                \Cognesy\HttpClient\Events\DebugRequestBodyUsed::class,
                \Cognesy\HttpClient\Events\DebugResponseBodyReceived::class,
                \Cognesy\HttpClient\Events\DebugStreamChunkReceived::class,
            ]
        );
    }
}