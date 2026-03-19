<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Support;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Events\Dispatchers\SymfonyEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcherCore;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyDispatcher;

final class SymfonyEventBusFactory
{
    public function bridge(?SymfonyDispatcher $symfonyDispatcher = null): SymfonyEventDispatcher
    {
        return new SymfonyEventDispatcher($symfonyDispatcher ?? new SymfonyEventDispatcherCore);
    }

    public function bus(
        SymfonyConfigProvider $configProvider,
        SymfonyEventDispatcher $bridge,
    ): EventDispatcher {
        return new EventDispatcher(
            name: 'instructor.symfony',
            parent: $this->shouldBridgeToSymfony($configProvider) ? $bridge : null,
        );
    }

    private function shouldBridgeToSymfony(SymfonyConfigProvider $configProvider): bool
    {
        $value = $configProvider->get(
            'instructor.events.dispatch_to_symfony',
            $configProvider->get('instructor.events.bridge_to_symfony', true),
        );

        if (is_bool($value)) {
            return $value;
        }

        if (! is_scalar($value)) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }
}
