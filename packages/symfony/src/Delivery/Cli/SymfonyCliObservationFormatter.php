<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Delivery\Cli;

use Cognesy\Events\Contracts\CanFormatConsoleEvent;
use Cognesy\Events\Data\ConsoleEventLine;
use Cognesy\Instructor\Symfony\Delivery\Progress\RuntimeProgressUpdate;

final class SymfonyCliObservationFormatter implements CanFormatConsoleEvent
{
    public function format(object $event): ?ConsoleEventLine
    {
        if (! $event instanceof RuntimeProgressUpdate) {
            return null;
        }

        return new ConsoleEventLine(
            label: $event->status->label(),
            message: $event->message,
            color: $event->status->color(),
            context: $this->context($event),
        );
    }

    private function context(RuntimeProgressUpdate $event): string
    {
        return match ($event->operationId) {
            null => $event->source,
            default => $event->source.':'.substr($event->operationId, -8),
        };
    }
}
