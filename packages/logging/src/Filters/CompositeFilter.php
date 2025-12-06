<?php

declare(strict_types=1);

namespace Cognesy\Logging\Filters;

use Cognesy\Events\Event;
use Cognesy\Logging\Contracts\EventFilter;

/**
 * Combines multiple filters with AND logic
 */
final readonly class CompositeFilter implements EventFilter
{
    /** @var EventFilter[] */
    private array $filters;

    public function __construct(EventFilter ...$filters)
    {
        $this->filters = $filters;
    }

    public function __invoke(Event $event): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter($event)) {
                return false;
            }
        }

        return true;
    }
}