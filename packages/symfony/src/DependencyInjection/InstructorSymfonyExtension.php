<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class InstructorSymfonyExtension extends Extension
{
    /** @var list<string> */
    private const SERVICE_FILES = [
        'core.yaml',
        'polyglot.yaml',
        'events.yaml',
        'agent_ctrl.yaml',
        'agents.yaml',
        'sessions.yaml',
        'telemetry.yaml',
        'logging.yaml',
        'testing.yaml',
        'messenger.yaml',
    ];

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration;
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../resources/config'),
        );

        foreach (self::SERVICE_FILES as $file) {
            $loader->load($file);
        }

        $container->setParameter('instructor.symfony.config', $config);
    }

    public function getAlias(): string
    {
        return 'instructor';
    }
}
