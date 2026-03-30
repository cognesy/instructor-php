<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Delivery\Messenger;

final readonly class RuntimeObservationMessage
{
    public string $eventType;

    public function __construct(
        public object $event,
    ) {
        $this->eventType = $event::class;
    }
}
