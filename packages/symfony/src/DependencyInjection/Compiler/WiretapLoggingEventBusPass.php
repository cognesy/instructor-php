<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class WiretapLoggingEventBusPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasDefinition('instructor.logging.pipeline_listener')) {
            return;
        }

        $eventBusService = $this->eventBusService($container);
        if ($eventBusService === '') {
            return;
        }

        if (! $container->has($eventBusService)) {
            return;
        }

        $definition = $container->findDefinition($eventBusService);
        if ($this->hasMethodCall($definition, 'wiretap', 'instructor.logging.pipeline_listener')) {
            return;
        }

        $definition->addMethodCall('wiretap', [new Reference('instructor.logging.pipeline_listener')]);
    }

    private function eventBusService(ContainerBuilder $container): string
    {
        if (! $container->hasParameter('instructor.logging.event_bus_service')) {
            return '';
        }

        $service = $container->getParameter('instructor.logging.event_bus_service');

        return match (true) {
            ! is_string($service) => '',
            $service === '' => '',
            default => $service,
        };
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
