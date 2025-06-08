<?php

namespace Cognesy\Events;

use Cognesy\Events\Contracts\CanRegisterEventListeners;
use Cognesy\Events\Extras\PsrEventDispatcherAdapter;
use Psr\EventDispatcher\EventDispatcherInterface;

class EventHandlerFactory
{
    private EventDispatcherInterface $dispatcher;
    private CanRegisterEventListeners $listener;

    public function __construct(
        ?EventDispatcherInterface $events = null,
        ?CanRegisterEventListeners $listener = null,
    ) {
        $default = match(true) {
            ($events === null) && ($listener === null) => new EventDispatcher(),
            ($events === null) => new EventDispatcher(),
            ($listener === null) => new PsrEventDispatcherAdapter($events),
            default => null,
        };

        $this->dispatcher = $events ?? $default;
        $this->listener = $listener ?? $default;
    }

    public function dispatcher(): EventDispatcherInterface {
        return $this->dispatcher;
    }

    public function listener(): CanRegisterEventListeners {
        return $this->listener;
    }
}