<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\DependencyInjection\Compiler;

use Cognesy\Events\Contracts\CanHandleEvents;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class WiretapProgressEventBusPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $this->isEnabled($container)) {
            return;
        }

        if (! $container->hasDefinition('instructor.delivery.progress_bridge')) {
            return;
        }

        if (! $container->has(CanHandleEvents::class)) {
            return;
        }

        $definition = $container->findDefinition(CanHandleEvents::class);
        if ($this->hasMethodCall($definition, 'wiretap', 'instructor.delivery.progress_bridge')) {
            return;
        }

        $definition->addMethodCall('wiretap', [new Reference('instructor.delivery.progress_bridge')]);
    }

    private function isEnabled(ContainerBuilder $container): bool
    {
        if (! $container->hasParameter('instructor.delivery.progress.enabled')) {
            return true;
        }

        return (bool) $container->getParameter('instructor.delivery.progress.enabled');
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
