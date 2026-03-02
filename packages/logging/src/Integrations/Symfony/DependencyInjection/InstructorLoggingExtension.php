<?php

declare(strict_types=1);

namespace Cognesy\Logging\Integrations\Symfony\DependencyInjection;

use Cognesy\Logging\Factories\SymfonyLoggingFactory;
use Cognesy\Logging\Integrations\EventPipelineWiretap;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * DependencyInjection Extension for Instructor Logging
 */
class InstructorLoggingExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (!$config['enabled']) {
            return;
        }

        $this->configureLogging($container, $config);
    }

    private function configureLogging(ContainerBuilder $container, array $config): void
    {
        $factoryMethod = match ($config['preset']) {
            'production' => 'productionSetup',
            'default' => 'defaultSetup',
            'custom' => 'create',
        };

        $definition = $container->register('instructor_logging.pipeline_factory', SymfonyLoggingFactory::class);

        if ($factoryMethod === 'create') {
            $definition->setFactory([SymfonyLoggingFactory::class, $factoryMethod])
                ->setArguments([
                    new Reference('service_container'),
                    new Reference('logger'),
                    $config['config'],
                ]);
        } else {
            $definition->setFactory([SymfonyLoggingFactory::class, $factoryMethod])
                ->setArguments([
                    new Reference('service_container'),
                    new Reference('logger'),
                ]);
        }

        $container->register('instructor_logging.pipeline_listener', EventPipelineWiretap::class)
            ->setArguments([new Reference('instructor_logging.pipeline_factory')]);

        $container->setParameter('instructor_logging.event_bus_service', $config['event_bus_service']);
    }
}
