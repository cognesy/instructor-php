<?php

declare(strict_types=1);

namespace Cognesy\Logging\Factories;

use Cognesy\Logging\Enrichers\LazyEnricher;
use Cognesy\Logging\Filters\EventHierarchyFilter;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\PsrLoggerWriter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Cognesy\Events\Event;

/**
 * Factory for Laravel-specific logging configuration
 */
final class LaravelLoggingFactory
{
    /** @return callable(Event): void */
    public static function create(Application $app, array $config = []): callable
    {
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

        // Add Laravel-specific enrichment (lazy evaluation)
        $pipeline->enrich(LazyEnricher::framework(function () use ($app) {
            $request = $app->bound('request') ? $app->make('request') : null;

            if (!$request instanceof Request) {
                return [];
            }

            return [
                'request_id' => $request->header('X-Request-ID') ?? uniqid('req_'),
                'user_id' => optional($request->user())->id,
                'session_id' => $request->session()?->getId(),
                'route' => optional($request->route())?->getName(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
            ];
        }));

        // Add user context enrichment
        $pipeline->enrich(LazyEnricher::user(function () use ($app) {
            $request = $app->bound('request') ? $app->make('request') : null;
            if (!$request instanceof Request) {
                return [];
            }

            $user = $request->user();
            if (!$user) {
                return [];
            }

            return [
                'user_id' => $user->getAuthIdentifier(),
                'user_type' => $user::class,
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
        $logger = $app['log']->channel($config['channel']);
        $pipeline->write(new PsrLoggerWriter($logger));

        return $pipeline->build();
    }

    /**
     * Quick setup for common Laravel patterns
     * @return callable(Event): void
     */
    public static function defaultSetup(Application $app): callable
    {
        return self::create($app, [
            'channel' => 'instructor',
            'level' => config('logging.default_level', 'debug'),
            'exclude_events' => config('instructor.logging.exclude_events', []),
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
     * @return callable(Event): void
     */
    public static function productionSetup(Application $app): callable
    {
        return self::create($app, [
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