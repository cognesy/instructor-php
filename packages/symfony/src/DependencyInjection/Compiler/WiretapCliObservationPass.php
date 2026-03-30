<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\DependencyInjection\Compiler;

use Cognesy\Instructor\Symfony\Delivery\Progress\Contracts\CanHandleProgressUpdates;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class WiretapCliObservationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $this->isEnabled($container)) {
            return;
        }

        if (! $container->has('instructor.delivery.cli.observer')) {
            return;
        }

        if (! $container->has(CanHandleProgressUpdates::class)) {
            return;
        }

        $definition = $container->findDefinition(CanHandleProgressUpdates::class);
        if ($this->hasMethodCall($definition, 'wiretap', 'instructor.delivery.cli.observer')) {
            return;
        }

        $definition->addMethodCall('wiretap', [new Reference('instructor.delivery.cli.observer')]);
    }

    private function isEnabled(ContainerBuilder $container): bool
    {
        if (! $container->hasParameter('instructor.delivery.cli.enabled')) {
            return false;
        }

        return (bool) $container->getParameter('instructor.delivery.cli.enabled');
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
