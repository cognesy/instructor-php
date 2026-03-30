<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\DependencyInjection;

use Cognesy\Agents\Session\Contracts\CanStoreSessions;
use Cognesy\Agents\Session\Store\FileSessionStore;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Instructor\Symfony\Delivery\Messenger\MessengerObservationBridge;
use Cognesy\Instructor\Symfony\Support\SymfonyLoggingFactory;
use Cognesy\Logging\Integrations\EventPipelineWiretap;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class InstructorSymfonyExtension extends Extension
{
    /** @var list<string> */
    private const SERVICE_FILES = [
        'core.yaml',
        'polyglot.yaml',
        'events.yaml',
        'delivery.yaml',
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
        $loggingInput = $this->loggingInput($configs);
        $configuration = new Configuration;
        $config = $this->normalizeFrameworkDefaults(
            $container,
            $this->processConfiguration($configuration, $configs),
        );

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../resources/config'),
        );

        $container->setParameter('instructor.symfony.config', $config);
        $container->setParameter('instructor.telemetry.config', $config['telemetry']);

        foreach (self::SERVICE_FILES as $file) {
            $loader->load($file);
        }

        $this->configureSessions($container, $config['sessions']);
        $this->configureProgressDelivery($container, $config['delivery']);
        $this->configureMessengerDelivery($container, $config['delivery']);
        $this->configureLogging($container, $config['logging'], $loggingInput);
    }

    public function getAlias(): string
    {
        return 'instructor';
    }

    /** @param array<int, array<string, mixed>> $configs */
    /** @return array<string, mixed> */
    private function loggingInput(array $configs): array
    {
        $input = [];

        foreach ($configs as $config) {
            $logging = $config['logging'] ?? null;
            if (! is_array($logging)) {
                continue;
            }

            $input = array_replace_recursive($input, $logging);
        }

        return $input;
    }

    /** @param array<string, mixed> $config */
    /** @param array<string, mixed> $loggingInput */
    private function configureLogging(ContainerBuilder $container, array $config, array $loggingInput): void
    {
        if (($config['enabled'] ?? false) !== true) {
            return;
        }

        if (($loggingInput['preset'] ?? null) === 'default') {
            @trigger_error(
                'Using instructor.logging.preset="default" is deprecated; use "development" instead.',
                E_USER_DEPRECATED,
            );
        }

        $config['_explicit'] = [
            'channel' => array_key_exists('channel', $loggingInput),
            'level' => array_key_exists('level', $loggingInput),
            'exclude_events' => array_key_exists('exclude_events', $loggingInput),
            'include_events' => array_key_exists('include_events', $loggingInput),
            'templates' => array_key_exists('templates', $loggingInput),
        ];

        $container->register('instructor.logging.pipeline_factory')
            ->setClass(\Closure::class)
            ->setFactory([new Reference(SymfonyLoggingFactory::class), 'make'])
            ->setArguments([
                new Reference('service_container'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config,
            ])
            ->setPublic(false);

        $container->register('instructor.logging.pipeline_listener', EventPipelineWiretap::class)
            ->setArguments([
                new Reference('instructor.logging.pipeline_factory'),
            ])
            ->setPublic(false);

        $container->setParameter(
            'instructor.logging.event_bus_service',
            (string) ($config['event_bus_service'] ?? ''),
        );
    }

    /** @param array<string, mixed> $config */
    private function configureSessions(ContainerBuilder $container, array $config): void
    {
        $driver = (string) ($config['store'] ?? 'memory');
        $directory = (string) (($config['file']['directory'] ?? '%kernel.cache_dir%/instructor/agent-sessions'));
        $storeService = match ($driver) {
            'file' => FileSessionStore::class,
            default => InMemorySessionStore::class,
        };

        $container->setParameter('instructor.sessions.store', $driver);
        $container->setParameter('instructor.sessions.file.directory', $directory);
        $container->setAlias(CanStoreSessions::class, $storeService)->setPublic(true);
    }

    /** @param array<string, mixed> $config */
    private function normalizeFrameworkDefaults(ContainerBuilder $container, array $config): array
    {
        $directory = (string) (($config['sessions']['file']['directory'] ?? ''));
        if (! str_contains($directory, '%kernel.cache_dir%')) {
            return $config;
        }

        $cacheDir = $container->hasParameter('kernel.cache_dir')
            ? (string) $container->getParameter('kernel.cache_dir')
            : sys_get_temp_dir().'/instructor-symfony-cache';

        $config['sessions']['file']['directory'] = str_replace('%kernel.cache_dir%', $cacheDir, $directory);

        return $config;
    }

    /** @param array<string, mixed> $config */
    private function configureMessengerDelivery(ContainerBuilder $container, array $config): void
    {
        $messenger = $config['messenger'] ?? null;

        if (! is_array($messenger) || ($messenger['enabled'] ?? false) !== true) {
            return;
        }

        $busService = (string) ($messenger['bus_service'] ?? 'message_bus');
        $observedEvents = array_values(array_filter(
            is_array($messenger['observe_events'] ?? null) ? $messenger['observe_events'] : [],
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        $container->register('instructor.delivery.messenger.observation_bridge', MessengerObservationBridge::class)
            ->setArguments([
                new Reference($busService, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $observedEvents,
            ])
            ->setPublic(false);
    }

    /** @param array<string, mixed> $config */
    private function configureProgressDelivery(ContainerBuilder $container, array $config): void
    {
        $progress = $config['progress'] ?? [];
        $cli = $config['cli'] ?? [];

        $container->setParameter('instructor.delivery.progress.enabled', (bool) ($progress['enabled'] ?? true));
        $container->setParameter('instructor.delivery.cli.enabled', (bool) ($cli['enabled'] ?? false));
        $container->setParameter('instructor.delivery.cli.use_colors', (bool) ($cli['use_colors'] ?? true));
        $container->setParameter('instructor.delivery.cli.show_timestamps', (bool) ($cli['show_timestamps'] ?? true));
    }
}
