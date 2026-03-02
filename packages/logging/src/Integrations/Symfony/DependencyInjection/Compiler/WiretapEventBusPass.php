<?php declare(strict_types=1);

namespace Cognesy\Logging\Integrations\Symfony\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class WiretapEventBusPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('instructor_logging.pipeline_listener')) {
            return;
        }

        $eventBusService = $this->eventBusService($container);
        if ($eventBusService === '') {
            return;
        }

        if (!$container->has($eventBusService)) {
            return;
        }

        $definition = $container->findDefinition($eventBusService);
        if ($this->hasMethodCall($definition, 'wiretap')) {
            return;
        }

        $definition->addMethodCall('wiretap', [new Reference('instructor_logging.pipeline_listener')]);
    }

    private function eventBusService(ContainerBuilder $container): string
    {
        if (!$container->hasParameter('instructor_logging.event_bus_service')) {
            return '';
        }

        $service = $container->getParameter('instructor_logging.event_bus_service');
        if (!is_string($service) || $service === '') {
            return '';
        }

        return $service;
    }

    private function hasMethodCall(Definition $definition, string $methodName): bool
    {
        foreach ($definition->getMethodCalls() as [$method]) {
            if ($method === $methodName) {
                return true;
            }
        }

        return false;
    }
}
