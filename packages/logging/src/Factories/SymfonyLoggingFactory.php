<?php

declare(strict_types=1);

namespace Cognesy\Logging\Factories;

use Cognesy\Logging\Enrichers\LazyEnricher;
use Cognesy\Logging\Filters\EventHierarchyFilter;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\PsrLoggerWriter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Factory for Symfony-specific logging configuration
 */
final class SymfonyLoggingFactory
{
    public static function create(
        ContainerInterface $container,
        LoggerInterface $logger,
        array $config = []
    ): callable {
        $config = array_merge([
            'channel' => 'instructor',
            'level' => 'debug',
            'exclude_events' => [],
            'include_events' => [],
            'templates' => [],
        ], $config);

        $pipeline = LoggingPipeline::create();

        // Add level filter
        if ($config['level']) {
            $pipeline->filter(new LogLevelFilter($config['level']));
        }

        // Add event class filtering
        if (!empty($config['exclude_events']) || !empty($config['include_events'])) {
            $pipeline->filter(new EventHierarchyFilter(
                excludedClasses: $config['exclude_events'],
                includedClasses: $config['include_events'],
            ));
        }

        // Add Symfony-specific enrichment (lazy evaluation)
        $pipeline->enrich(LazyEnricher::framework(function () use ($container) {
            if (!$container->has('request_stack')) {
                return [];
            }

            /** @var RequestStack $requestStack */
            $requestStack = $container->get('request_stack');
            $request = $requestStack->getCurrentRequest();

            if (!$request) {
                return [];
            }

            return [
                'request_id' => $request->headers->get('X-Request-ID') ?? uniqid('req_'),
                'session_id' => $request->hasSession() ? $request->getSession()->getId() : null,
                'route' => $request->attributes->get('_route'),
                'method' => $request->getMethod(),
                'url' => $request->getUri(),
                'client_ip' => $request->getClientIp(),
            ];
        }));

        // Add user context enrichment
        $pipeline->enrich(LazyEnricher::user(function () use ($container) {
            if (!$container->has('security.token_storage')) {
                return [];
            }

            /** @var TokenStorageInterface $tokenStorage */
            $tokenStorage = $container->get('security.token_storage');
            $token = $tokenStorage->getToken();

            if (!$token || !$token->getUser()) {
                return [];
            }

            $user = $token->getUser();

            return [
                'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
                'username' => $user->getUserIdentifier(),
                'user_type' => $user::class,
                'roles' => $token->getRoleNames(),
            ];
        }));

        // Add message formatting
        if (!empty($config['templates'])) {
            $pipeline->format(new MessageTemplateFormatter(
                templates: $config['templates'],
                channel: $config['channel'],
            ));
        }

        // Add PSR-3 logger writer
        $pipeline->write(new PsrLoggerWriter($logger));

        return $pipeline->build();
    }

    /**
     * Quick setup for common Symfony patterns
     */
    public static function defaultSetup(
        ContainerInterface $container,
        LoggerInterface $logger
    ): callable {
        return self::create($container, $logger, [
            'channel' => 'instructor',
            'level' => $container->hasParameter('kernel.debug') && $container->getParameter('kernel.debug')
                ? 'debug'
                : 'info',
            'templates' => [
                \Cognesy\Instructor\Events\StructuredOutputStarted::class =>
                    'Starting {responseClass} generation with {model}',
                \Cognesy\Instructor\Events\ResponseValidationFailed::class =>
                    'Validation failed for {responseClass}: {error}',
                \Cognesy\HttpClient\Events\HttpRequestSent::class =>
                    'HTTP {method} {url}',
            ],
        ]);
    }

    /**
     * Setup for production with minimal logging
     */
    public static function productionSetup(
        ContainerInterface $container,
        LoggerInterface $logger
    ): callable {
        return self::create($container, $logger, [
            'channel' => 'instructor',
            'level' => 'warning',
            'exclude_events' => [
                \Cognesy\HttpClient\Events\DebugRequestBodyUsed::class,
                \Cognesy\HttpClient\Events\DebugResponseBodyReceived::class,
                \Cognesy\Instructor\Events\PartialResponseGenerated::class,
            ],
        ]);
    }
}