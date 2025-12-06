<?php

declare(strict_types=1);

namespace Cognesy\Logging\Enrichers;

use Cognesy\Events\Event;
use Cognesy\Logging\Contracts\EventEnricher;
use Cognesy\Logging\LogContext;

/**
 * Basic enricher that provides event context without additional data
 */
final readonly class BaseEnricher implements EventEnricher
{
    public function __invoke(Event $event): LogContext
    {
        return LogContext::fromEvent($event);
    }
}