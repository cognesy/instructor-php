<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Delivery\Progress;

use Cognesy\Instructor\Symfony\Delivery\Progress\Contracts\CanHandleProgressUpdates;

final readonly class RuntimeProgressBridge
{
    public function __construct(
        private CanHandleProgressUpdates $progressUpdates,
        private RuntimeProgressProjector $projector,
    ) {}

    public function __invoke(object $event): void
    {
        $update = $this->projector->project($event);

        if ($update === null) {
            return;
        }

        $this->progressUpdates->dispatch($update);
    }
}
