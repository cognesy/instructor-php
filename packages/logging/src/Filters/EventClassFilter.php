<?php

declare(strict_types=1);

namespace Cognesy\Logging\Filters;

use Cognesy\Events\Event;
use Cognesy\Logging\Contracts\EventFilter;

/**
 * Filters events by class inclusion/exclusion
 */
final readonly class EventClassFilter implements EventFilter
{
    public function __construct(
        private array $excludedClasses = [],
        private array $includedClasses = [],
    ) {}

    public function __invoke(Event $event): bool
    {
        $eventClass = $event::class;

        // If included classes specified, only allow those
        if (!empty($this->includedClasses)) {
            return in_array($eventClass, $this->includedClasses, true);
        }

        // Otherwise, exclude specified classes
        return !in_array($eventClass, $this->excludedClasses, true);
    }
}