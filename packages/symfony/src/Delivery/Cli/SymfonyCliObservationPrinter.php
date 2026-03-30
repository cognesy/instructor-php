<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Delivery\Cli;

use Cognesy\Events\Support\ConsoleEventPrinter;

final readonly class SymfonyCliObservationPrinter
{
    private ConsoleEventPrinter $printer;

    public function __construct(
        private SymfonyCliObservationFormatter $formatter,
        bool $useColors = true,
        bool $showTimestamps = true,
    ) {
        $this->printer = new ConsoleEventPrinter(
            useColors: $useColors,
            showTimestamps: $showTimestamps,
        );
    }

    public function __invoke(object $event): void
    {
        $this->printer->printIfAny($this->formatter->format($event));
    }
}
