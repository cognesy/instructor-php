<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony;

use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Instructor\Symfony\Agents\AgentRegistryTags;
use Cognesy\Instructor\Symfony\Agents\SchemaRegistration;
use Cognesy\Instructor\Symfony\DependencyInjection\Compiler\WiretapCliObservationPass;
use Cognesy\Instructor\Symfony\DependencyInjection\Compiler\RegisterNativeAgentContributionsPass;
use Cognesy\Instructor\Symfony\DependencyInjection\Compiler\WiretapMessengerObservationPass;
use Cognesy\Instructor\Symfony\DependencyInjection\Compiler\WiretapLoggingEventBusPass;
use Cognesy\Instructor\Symfony\DependencyInjection\Compiler\WiretapProgressEventBusPass;
use Cognesy\Instructor\Symfony\DependencyInjection\Compiler\WiretapTelemetryEventBusPass;
use Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class InstructorSymfonyBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->registerNativeAgentAutoconfiguration($container);
        $container->addCompilerPass(new RegisterNativeAgentContributionsPass);
        $container->addCompilerPass(new WiretapProgressEventBusPass);
        $container->addCompilerPass(new WiretapCliObservationPass);
        $container->addCompilerPass(new WiretapMessengerObservationPass);
        $container->addCompilerPass(new WiretapLoggingEventBusPass);
        $container->addCompilerPass(new WiretapTelemetryEventBusPass);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new InstructorSymfonyExtension;
    }

    private function registerNativeAgentAutoconfiguration(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(ToolInterface::class)
            ->addTag(AgentRegistryTags::TOOLS);

        $container->registerForAutoconfiguration(CanProvideAgentCapability::class)
            ->addTag(AgentRegistryTags::CAPABILITIES);

        $container->registerForAutoconfiguration(AgentDefinition::class)
            ->addTag(AgentRegistryTags::DEFINITIONS);

        $container->registerForAutoconfiguration(SchemaRegistration::class)
            ->addTag(AgentRegistryTags::SCHEMAS);
    }
}
