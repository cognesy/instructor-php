<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

/**
 * Implemented by components that can have their event emitter injected or swapped.
 */
interface CanAcceptAgentEventEmitter
{
    public function withEventEmitter(CanEmitAgentEvents $eventEmitter): static;
}
