<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\DependencyInjection\Compiler;

use Cognesy\Events\Contracts\CanHandleEvents;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class WiretapMessengerObservationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasDefinition('instructor.delivery.messenger.observation_bridge')) {
            return;
        }

        if (! $container->has(CanHandleEvents::class)) {
            return;
        }

        $definition = $container->findDefinition(CanHandleEvents::class);
        if ($this->hasMethodCall($definition, 'wiretap', 'instructor.delivery.messenger.observation_bridge')) {
            return;
        }

        $definition->addMethodCall('wiretap', [new Reference('instructor.delivery.messenger.observation_bridge')]);
    }

    private function hasMethodCall(Definition $definition, string $methodName, string $serviceId): bool
    {
        foreach ($definition->getMethodCalls() as [$method, $arguments]) {
            $listener = $arguments[0] ?? null;

            if ($method === $methodName && $listener instanceof Reference && (string) $listener === $serviceId) {
                return true;
            }
        }

        return false;
    }
}
