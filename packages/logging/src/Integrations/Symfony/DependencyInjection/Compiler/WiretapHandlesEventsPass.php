<?php declare(strict_types=1);

namespace Cognesy\Logging\Integrations\Symfony\DependencyInjection\Compiler;

use Cognesy\Events\Traits\HandlesEvents;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class WiretapHandlesEventsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('instructor_logging.pipeline_factory')) {
            return;
        }

        foreach ($container->getDefinitions() as $definition) {
            $class = $definition->getClass();
            if (!is_string($class) || $class === '') {
                continue;
            }

            $resolvedClass = $container->getParameterBag()->resolveValue($class);
            if (!is_string($resolvedClass) || $resolvedClass === '' || !class_exists($resolvedClass)) {
                continue;
            }

            if (!$this->usesTrait($resolvedClass, HandlesEvents::class)) {
                continue;
            }

            if ($this->hasMethodCall($definition, 'wiretap')) {
                continue;
            }

            $definition->addMethodCall('wiretap', [new Reference('instructor_logging.pipeline_factory')]);
        }
    }

    private function usesTrait(string $className, string $traitName): bool
    {
        $currentClass = $className;
        while (true) {
            $traits = class_uses($currentClass) ?: [];
            if (in_array($traitName, $traits, true)) {
                return true;
            }

            $parentClass = get_parent_class($currentClass);
            if (!is_string($parentClass) || $parentClass === '') {
                return false;
            }

            $currentClass = $parentClass;
        }
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
