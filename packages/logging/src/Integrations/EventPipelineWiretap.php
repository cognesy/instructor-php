<?php

declare(strict_types=1);

namespace Cognesy\Logging\Integrations;

use Cognesy\Events\Event;

final readonly class EventPipelineWiretap
{
    /** @param callable(Event): void $pipeline */
    public function __construct(
        private mixed $pipeline,
    ) {}

    public function __invoke(object $event): void
    {
        if (!$event instanceof Event) {
            return;
        }

        ($this->pipeline)($event);
    }
}
