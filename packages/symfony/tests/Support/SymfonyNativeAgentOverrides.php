<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

use Closure;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Instructor\Symfony\Agents\AgentRegistryTags;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class SymfonyNativeAgentOverrides
{
    public static function definition(AgentDefinition $definition, ?string $serviceId = null): Closure
    {
        $registered = SymfonyTestServiceRegistry::put($definition);
        $resolvedServiceId = $serviceId ?? $definition->name.'.definition';

        return static function (ContainerBuilder $container) use ($registered, $resolvedServiceId): void {
            $container->setDefinition($resolvedServiceId, (new Definition(AgentDefinition::class))
                ->setFactory([SymfonyTestServiceRegistry::class, 'get'])
                ->setArguments([$registered])
                ->setPublic(true)
                ->addTag(AgentRegistryTags::DEFINITIONS));
        };
    }

    public static function loopFactory(CanInstantiateAgentLoop $factory): Closure
    {
        $registered = SymfonyTestServiceRegistry::put($factory);

        return static function (ContainerBuilder $container) use ($registered): void {
            $container->setDefinition(CanInstantiateAgentLoop::class, (new Definition(CanInstantiateAgentLoop::class))
                ->setFactory([SymfonyTestServiceRegistry::class, 'get'])
                ->setArguments([$registered])
                ->setPublic(true));
        };
    }
}
