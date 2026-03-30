<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Delivery\Messenger;

use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerObservationBridge
{
    /**
     * @param list<class-string|'*'> $observedEvents
     */
    public function __construct(
        private ?MessageBusInterface $bus,
        private array $observedEvents,
    ) {}

    public function __invoke(object $event): void
    {
        if ($this->bus === null || ! $this->supports($event)) {
            return;
        }

        $this->bus->dispatch(new RuntimeObservationMessage($event));
    }

    private function supports(object $event): bool
    {
        foreach ($this->observedEvents as $observedEvent) {
            if ($observedEvent === '*' || is_a($event, $observedEvent)) {
                return true;
            }
        }

        return false;
    }
}
