<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Logging;

use Cognesy\Events\Event;
use Cognesy\Logging\Enrichers\LazyEnricher;
use Cognesy\Logging\Filters\EventHierarchyFilter;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\PsrLoggerWriter;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

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

        if ($config['level']) {
            $pipeline->filter(new LogLevelFilter($config['level']));
        }

        if (!empty($config['exclude_events']) || !empty($config['include_events'])) {
            $pipeline->filter(new EventHierarchyFilter(
                excludedClasses: $config['exclude_events'],
                includedClasses: $config['include_events'],
            ));
        }

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

        if (!empty($config['templates'])) {
            $pipeline->format(new MessageTemplateFormatter(
                templates: $config['templates'],
                channel: $config['channel'],
            ));
        }

        $logger = $app['log']->channel($config['channel']);
        $pipeline->write(new PsrLoggerWriter($logger));

        return $pipeline->build();
    }

    /** @return callable(Event): void */
    public static function defaultSetup(Application $app): callable
    {
        $channel = (string) self::configGet($app, 'instructor.logging.channel', 'instructor');
        $level = (string) self::configGet($app, 'instructor.logging.level', 'warning');
        $excludeEvents = self::configGet($app, 'instructor.logging.exclude_events', [
            \Cognesy\Http\Events\DebugRequestBodyUsed::class,
            \Cognesy\Http\Events\DebugResponseBodyReceived::class,
        ]);

        return self::create($app, [
            'channel' => $channel,
            'level' => $level,
            'exclude_events' => is_array($excludeEvents) ? $excludeEvents : [],
            'templates' => [
                \Cognesy\Instructor\Events\StructuredOutputStarted::class =>
                    'Starting {responseClass} generation with {model}',
                \Cognesy\Instructor\Events\ResponseValidationFailed::class =>
                    'Validation failed for {responseClass}: {error}',
                \Cognesy\Http\Events\HttpRequestSent::class =>
                    'HTTP {method} {url}',
            ],
        ]);
    }

    /** @return callable(Event): void */
    public static function productionSetup(Application $app): callable
    {
        $channel = (string) self::configGet($app, 'instructor.logging.channel', 'instructor');

        return self::create($app, [
            'channel' => $channel,
            'level' => 'warning',
            'exclude_events' => [
                \Cognesy\Http\Events\DebugRequestBodyUsed::class,
                \Cognesy\Http\Events\DebugResponseBodyReceived::class,
                \Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated::class,
            ],
        ]);
    }

    private static function configGet(Application $app, string $path, mixed $default): mixed
    {
        $config = match (true) {
            !$app->bound('config') => null,
            default => $app->make('config'),
        };

        return match (true) {
            $config instanceof ConfigRepository => $config->get($path, $default),
            is_object($config) && method_exists($config, 'get') => $config->get($path, $default),
            default => $default,
        };
    }
}
