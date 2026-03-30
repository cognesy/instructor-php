<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\DependencyInjection\Compiler;

use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\StructuredOutput\SchemaDefinition;
use Cognesy\Agents\Capability\StructuredOutput\SchemaRegistry;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Instructor\Symfony\Agents\AgentRegistryTags;
use Cognesy\Instructor\Symfony\Agents\SchemaRegistration;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterNativeAgentContributionsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->registerDefinitions($container);
        $this->registerTools($container);
        $this->registerCapabilities($container);
        $this->registerSchemas($container);
    }

    private function registerDefinitions(ContainerBuilder $container): void
    {
        $registry = $container->findDefinition(AgentDefinitionRegistry::class);

        foreach ($container->findTaggedServiceIds(AgentRegistryTags::DEFINITIONS) as $id => $_tags) {
            $this->assertServiceType($container, $id, AgentDefinition::class, $this->typeErrorMessage(AgentDefinition::class));
            $registry->addMethodCall('register', [new Reference($id)]);
        }
    }

    private function registerTools(ContainerBuilder $container): void
    {
        $registry = $container->findDefinition(ToolRegistry::class);

        foreach ($container->findTaggedServiceIds(AgentRegistryTags::TOOLS) as $id => $_tags) {
            $this->assertServiceType($container, $id, ToolInterface::class, $this->typeErrorMessage(ToolInterface::class));
            $registry->addMethodCall('register', [new Reference($id)]);
        }
    }

    private function registerCapabilities(ContainerBuilder $container): void
    {
        $registry = $container->findDefinition(AgentCapabilityRegistry::class);

        foreach ($container->findTaggedServiceIds(AgentRegistryTags::CAPABILITIES) as $id => $tags) {
            $class = $this->assertServiceType(
                $container,
                $id,
                CanProvideAgentCapability::class,
                $this->typeErrorMessage(CanProvideAgentCapability::class),
            );

            $registry->addMethodCall('register', [
                $this->capabilityName($tags, $class),
                new Reference($id),
            ]);
        }
    }

    private function registerSchemas(ContainerBuilder $container): void
    {
        $registry = $container->findDefinition(SchemaRegistry::class);

        foreach ($container->findTaggedServiceIds(AgentRegistryTags::SCHEMAS) as $id => $tags) {
            $definition = $container->findDefinition($id);
            $class = $this->serviceClass($definition, $id);

            if (!is_a($class, SchemaRegistration::class, true)) {
                throw new InvalidArgumentException('Tagged native agent schemas must resolve to SchemaRegistration services.');
            }

            $registry->addMethodCall('register', [
                $this->schemaName($definition, $id),
                $this->schemaValue($definition, $id),
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $tags
     * @return class-string
     */
    private function assertServiceType(
        ContainerBuilder $container,
        string $id,
        string $expectedType,
        string $message,
    ): string {
        $class = $this->serviceClass($container->findDefinition($id), $id);

        if (is_a($class, $expectedType, true)) {
            return $class;
        }

        throw new InvalidArgumentException($message);
    }

    private function capabilityName(array $tags, string $class): string
    {
        foreach ($tags as $tag) {
            $name = $tag['alias'] ?? $tag['capability'] ?? $tag['name'] ?? null;

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return $class::capabilityName();
    }

    private function schemaName(Definition $definition, string $id): string
    {
        $name = $this->definitionArgument($definition, 'name', 0);

        if (is_string($name) && $name !== '') {
            return $name;
        }

        throw new InvalidArgumentException("Schema registration service '{$id}' must define a non-empty name argument.");
    }

    private function schemaValue(Definition $definition, string $id): string|SchemaDefinition
    {
        $schema = $this->definitionArgument($definition, 'schema', 1);

        if (is_string($schema) && $schema !== '') {
            return $schema;
        }

        if ($schema instanceof SchemaDefinition) {
            return $schema;
        }

        throw new InvalidArgumentException("Schema registration service '{$id}' must define a class-string or SchemaDefinition schema argument.");
    }

    private function definitionArgument(Definition $definition, string $name, int $index): mixed
    {
        $arguments = $definition->getArguments();

        return match (true) {
            array_key_exists('$'.$name, $arguments) => $arguments['$'.$name],
            array_key_exists($name, $arguments) => $arguments[$name],
            array_key_exists($index, $arguments) => $arguments[$index],
            default => null,
        };
    }

    /**
     * @return class-string
     */
    private function serviceClass(Definition $definition, string $id): string
    {
        $class = $definition->getClass();

        if (is_string($class) && $class !== '') {
            return $class;
        }

        throw new InvalidArgumentException("Tagged native agent service '{$id}' must declare a concrete class.");
    }

    private function typeErrorMessage(string $type): string
    {
        return match ($type) {
            AgentDefinition::class => 'Tagged native agent definitions must resolve to AgentDefinition instances.',
            ToolInterface::class => 'Tagged native agent tools must resolve to ToolInterface services.',
            CanProvideAgentCapability::class => 'Tagged native agent capabilities must resolve to CanProvideAgentCapability services.',
            default => 'Tagged native agent service has an invalid type.',
        };
    }
}
