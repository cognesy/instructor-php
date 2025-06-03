<?php

namespace Cognesy\Utils\Events;

use Cognesy\Utils\Events\Contracts\CanRegisterEventListeners;
use Cognesy\Utils\Events\Extras\PsrEventDispatcherAdapter;
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