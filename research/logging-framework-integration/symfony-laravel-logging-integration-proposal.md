# Instructor-PHP Framework Logging Integration Proposal

## Executive Summary

This document proposes enhancements to make Instructor's `Inference`, `StructuredOutput`, and `Embeddings` classes seamlessly integrate with Symfony and Laravel logging infrastructure. The proposal builds on the existing event-driven architecture while adding dedicated logging interfaces and framework-specific bindings to improve developer experience (DX).

## Current State Analysis

### Existing Logging Architecture

Instructor-PHP currently implements a sophisticated event-driven logging system:

- **PSR-3 Compliant**: Uses standard logging interface
- **Event-Based**: All operations emit structured events with log levels
- **Framework Adapters**: Existing `LaravelEventDispatcher` and `SymfonyEventDispatcher`
- **Flexible Integration**: Wiretap pattern for global event capture

### Current Integration Pattern

```php
// Current approach requires manual logger setup
$logger = app('log'); // Laravel
$user = (new StructuredOutput)
    ->withEventHandler(app('events'))
    ->wiretap(fn(Event $e) => $logger->log($e->logLevel, $e->name(), ['data' => $e->data]))
    ->withMessages("Extract user data")
    ->withResponseClass(User::class)
    ->get();
```

### Gaps Identified

1. **Manual Logger Binding**: Developers must manually wire PSR-3 loggers
2. **Boilerplate Code**: Repetitive event-to-log conversion logic
3. **Missing Framework Integration**: No dedicated Symfony Bundle or enhanced Laravel ServiceProvider
4. **Context Loss**: Framework-specific context (request IDs, user sessions) not automatically captured
5. **Performance Overhead**: No built-in filtering or batch logging capabilities

## Proposed Architecture

### 1. Core Logging Interfaces

#### New Interface: `LoggerAware`

```php
<?php

namespace Cognesy\Instructor\Contracts;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;

interface LoggerAware extends LoggerAwareInterface
{
    /**
     * Enable/disable automatic logging
     */
    public function withLogging(bool $enabled = true): static;

    /**
     * Set minimum log level for automatic logging
     */
    public function withLogLevel(string $level): static;

    /**
     * Add contextual data to all log entries
     */
    public function withLogContext(array $context): static;

    /**
     * Enable performance metrics logging
     */
    public function withMetricsLogging(bool $enabled = true): static;
}
```

#### Enhanced Event-to-Log Converter

```php
<?php

namespace Cognesy\Instructor\Logging;

use Cognesy\Events\Event;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class EventLogConverter
{
    public function __construct(
        private LoggerInterface $logger,
        private array $contextProviders = [],
        private string $minimumLevel = LogLevel::DEBUG,
        private bool $includeMetrics = true,
    ) {}

    public function __invoke(Event $event): void
    {
        if (!$this->shouldLog($event)) {
            return;
        }

        $context = $this->buildContext($event);
        $message = $this->formatMessage($event);

        $this->logger->log($event->logLevel, $message, $context);
    }

    private function buildContext(Event $event): array
    {
        $context = [
            'event_id' => $event->id,
            'event_class' => $event::class,
            'timestamp' => $event->createdAt->format(\DateTime::ISO8601),
        ];

        // Add event data
        if ($event->data !== null) {
            $context['event_data'] = $this->sanitizeData($event->data);
        }

        // Add contextual providers (request ID, user ID, etc.)
        foreach ($this->contextProviders as $provider) {
            $context = array_merge($context, $provider->getContext());
        }

        // Add performance metrics
        if ($this->includeMetrics && $this->isPerformanceEvent($event)) {
            $context['metrics'] = $this->extractMetrics($event);
        }

        return $context;
    }

    private function formatMessage(Event $event): string
    {


    }
}
```

### 2. Framework-Specific Enhancements

#### Laravel Integration

##### Enhanced ServiceProvider

```php
<?php

namespace Cognesy\Instructor\Laravel;

use Illuminate\Support\ServiceProvider;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Inference\Inference;
use Cognesy\Instructor\Embeddings\Embeddings;
use Cognesy\Instructor\Logging\EventLogConverter;
use Cognesy\Instructor\Laravel\Logging\LaravelContextProvider;

class InstructorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register core services with automatic logger injection
        $this->app->bind(StructuredOutput::class, function ($app) {
            $instance = new StructuredOutput(
                events: $app['events'],
                configProvider: $app['config']
            );

            if ($this->shouldAutoEnableLogging()) {
                $this->configureLogging($instance, $app);
            }

            return $instance;
        });

        $this->app->bind(Inference::class, function ($app) {
            $instance = new Inference(
                events: $app['events'],
                configProvider: $app['config']
            );

            if ($this->shouldAutoEnableLogging()) {
                $this->configureLogging($instance, $app);
            }

            return $instance;
        });

        $this->app->bind(Embeddings::class, function ($app) {
            $instance = new Embeddings(
                events: $app['events'],
                configProvider: $app['config']
            );

            if ($this->shouldAutoEnableLogging()) {
                $this->configureLogging($instance, $app);
            }

            return $instance;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/instructor.php' => config_path('instructor.php'),
        ], 'instructor-config');
    }

    private function configureLogging($instance, $app): void
    {
        $logChannel = config('instructor.logging.channel', 'default');
        $logger = $app['log']->channel($logChannel);

        $contextProviders = [
            new LaravelContextProvider($app['request']),
        ];

        $converter = new EventLogConverter(
            logger: $logger,
            contextProviders: $contextProviders,
            minimumLevel: config('instructor.logging.level', 'debug'),
            includeMetrics: config('instructor.logging.metrics', true),
        );

        $instance->wiretap($converter);

        // Set logger on instance if it implements LoggerAware
        if ($instance instanceof LoggerAware) {
            $instance->setLogger($logger);
        }
    }

    private function shouldAutoEnableLogging(): bool
    {
        return config('instructor.logging.auto_enable', true);
    }
}
```

##### Laravel Context Provider

```php
<?php

namespace Cognesy\Instructor\Laravel\Logging;

use Illuminate\Http\Request;
use Cognesy\Instructor\Contracts\ContextProvider;

class LaravelContextProvider implements ContextProvider
{
    public function __construct(private ?Request $request = null) {}

    public function getContext(): array
    {
        if (!$this->request) {
            return [];
        }

        return [
            'request_id' => $this->request->header('X-Request-ID') ?? uniqid(),
            'user_id' => optional($this->request->user())->id,
            'session_id' => $this->request->session()?->getId(),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'route' => optional($this->request->route())->getName(),
            'method' => $this->request->method(),
            'url' => $this->request->url(),
        ];
    }
}
```

##### Laravel Configuration

```php
<?php

// config/instructor.php
return [
    'logging' => [
        'auto_enable' => env('INSTRUCTOR_LOGGING', true),
        'channel' => env('INSTRUCTOR_LOG_CHANNEL', 'instructor'),
        'level' => env('INSTRUCTOR_LOG_LEVEL', 'debug'),
        'metrics' => env('INSTRUCTOR_LOG_METRICS', true),

        'filters' => [
            // Only log events above certain levels in production
            'production' => [
                'minimum_level' => 'info',
                'exclude_events' => [
                    \Cognesy\Instructor\Events\PartialResponseGenerated::class,
                ],
            ],
        ],

        'formatters' => [
            // Custom message formatters per event type
            \Cognesy\Instructor\Events\StructuredOutputStarted::class =>
                'Starting {responseClass} generation with {model}',
            \Cognesy\Instructor\Events\ResponseValidationFailed::class =>
                'Validation failed for {responseClass}: {error}',
        ],
    ],

    'channels' => [
        'instructor' => [
            'driver' => 'daily',
            'path' => storage_path('logs/instructor.log'),
            'level' => 'debug',
            'days' => 14,
            'formatter' => \Cognesy\Instructor\Laravel\Logging\InstructorFormatter::class,
        ],

        'instructor_errors' => [
            'driver' => 'single',
            'path' => storage_path('logs/instructor-errors.log'),
            'level' => 'error',
        ],

        'instructor_metrics' => [
            'driver' => 'single',
            'path' => storage_path('logs/instructor-metrics.log'),
            'level' => 'info',
            'formatter' => \Cognesy\Instructor\Laravel\Logging\MetricsFormatter::class,
        ],
    ],
];
```

#### Symfony Integration

##### Symfony Bundle

```php
<?php

namespace Cognesy\Instructor\Symfony;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Cognesy\Instructor\Symfony\DependencyInjection\InstructorExtension;

class InstructorBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new LoggerIntegrationPass());
    }

    public function getContainerExtension(): InstructorExtension
    {
        return new InstructorExtension();
    }
}
```

##### Symfony DI Extension

```php
<?php

namespace Cognesy\Instructor\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class InstructorExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->configureLogging($container, $config['logging']);
    }

    private function configureLogging(ContainerBuilder $container, array $config): void
    {
        if (!$config['enabled']) {
            return;
        }

        // Register event log converter
        $container->register('instructor.logging.event_converter', EventLogConverter::class)
            ->setArguments([
                new Reference($config['logger']),
                [], // context providers - injected by compiler pass
                $config['level'],
                $config['metrics'],
            ]);

        // Register Symfony context provider
        $container->register('instructor.logging.symfony_context', SymfonyContextProvider::class)
            ->setArguments([
                new Reference('request_stack'),
                new Reference('security.token_storage'),
            ]);

        // Configure auto-logging for all Instructor services
        foreach (['structured_output', 'inference', 'embeddings'] as $service) {
            $definition = $container->getDefinition("instructor.{$service}");
            $definition->addMethodCall('wiretap', [new Reference('instructor.logging.event_converter')]);
        }
    }
}
```

##### Symfony Services Configuration

```yaml
# Resources/config/services.yaml
services:
  instructor.structured_output:
    class: Cognesy\Instructor\StructuredOutput
    arguments:
      - '@instructor.event_dispatcher'
      - '@instructor.config_provider'
    calls:
      - ['setLogger', ['@logger']]
    tags:
      - { name: monolog.logger, channel: instructor }

  instructor.inference:
    class: Cognesy\Instructor\Inference\Inference
    arguments:
      - '@instructor.event_dispatcher'
      - '@instructor.config_provider'
    calls:
      - ['setLogger', ['@logger']]
    tags:
      - { name: monolog.logger, channel: instructor }

  instructor.embeddings:
    class: Cognesy\Instructor\Embeddings\Embeddings
    arguments:
      - '@instructor.event_dispatcher'
      - '@instructor.config_provider'
    calls:
      - ['setLogger', ['@logger']]
    tags:
      - { name: monolog.logger, channel: instructor }

  instructor.event_dispatcher:
    class: Cognesy\Events\Dispatchers\SymfonyEventDispatcher
    arguments:
      - '@event_dispatcher'

  instructor.config_provider:
    class: Cognesy\Instructor\Config\DefaultConfigProvider
```

### 3. Enhanced Core Classes Implementation

#### Updated StructuredOutput Class

```php
<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Contracts\LoggerAware;
use Cognesy\Instructor\Traits\HandlesLogging;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class StructuredOutput implements LoggerAware
{
    use HandlesEvents;
    use HandlesLogging; // New trait

    private LoggerInterface $logger;
    private bool $loggingEnabled = false;
    private string $logLevel = LogLevel::DEBUG;
    private array $logContext = [];
    private bool $metricsEnabled = false;

    public function __construct(
        ?CanHandleEvents $events = null,
        ?CanProvideConfig $configProvider = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->configProvider = $configProvider ?? new DefaultConfigProvider();
        $this->logger = $logger ?? new NullLogger();
    }

    // LoggerAware implementation
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;

        // Auto-enable logging when logger is set
        if (!$this->loggingEnabled && !$logger instanceof NullLogger) {
            $this->withLogging(true);
        }
    }

    public function withLogging(bool $enabled = true): static
    {
        $this->loggingEnabled = $enabled;

        if ($enabled && !$this->hasLoggerWiretap()) {
            $this->setupAutoLogging();
        }

        return $this;
    }

    public function withLogLevel(string $level): static
    {
        $this->logLevel = $level;
        return $this;
    }

    public function withLogContext(array $context): static
    {
        $this->logContext = array_merge($this->logContext, $context);
        return $this;
    }

    public function withMetricsLogging(bool $enabled = true): static
    {
        $this->metricsEnabled = $enabled;
        return $this;
    }

    // Existing methods remain unchanged...
    public function withMessages(array|string $messages): self { /* ... */ }
    public function withResponseClass(string $responseClass): self { /* ... */ }
    // ... other existing methods

    private function setupAutoLogging(): void
    {
        $converter = new EventLogConverter(
            logger: $this->logger,
            contextProviders: [],
            minimumLevel: $this->logLevel,
            includeMetrics: $this->metricsEnabled,
        );

        // Add base context
        if (!empty($this->logContext)) {
            $converter = $converter->withBaseContext($this->logContext);
        }

        $this->wiretap($converter);
    }

    private function hasLoggerWiretap(): bool
    {
        // Check if logging wiretap is already registered
        return $this->events->hasListener('*', EventLogConverter::class);
    }
}
```

#### New Logging Trait

```php
<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Logging\MetricsCollector;
use Psr\Log\LogLevel;

trait HandlesLogging
{
    private array $performanceMetrics = [];

    protected function logPerformanceStart(string $operation, array $context = []): string
    {
        if (!$this->metricsEnabled) {
            return '';
        }

        $metricId = uniqid($operation . '_');
        $this->performanceMetrics[$metricId] = [
            'operation' => $operation,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'context' => $context,
        ];

        return $metricId;
    }

    protected function logPerformanceEnd(string $metricId, array $additionalContext = []): void
    {
        if (!$this->metricsEnabled || !isset($this->performanceMetrics[$metricId])) {
            return;
        }

        $metric = $this->performanceMetrics[$metricId];
        $duration = (microtime(true) - $metric['start_time']) * 1000; // ms
        $memoryUsed = memory_get_usage(true) - $metric['start_memory'];

        $context = array_merge($metric['context'], $additionalContext, [
            'duration_ms' => round($duration, 2),
            'memory_used_bytes' => $memoryUsed,
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ]);

        $this->logger->info("Performance: {$metric['operation']}", $context);

        unset($this->performanceMetrics[$metricId]);
    }
}
```

### 4. Advanced Logging Features

#### Correlation ID Support

```php
<?php

namespace Cognesy\Instructor\Logging;

class CorrelationIdManager
{
    private static ?string $currentId = null;

    public static function generate(): string
    {
        return self::$currentId = uniqid('instr_', true);
    }

    public static function get(): ?string
    {
        return self::$currentId;
    }

    public static function set(string $id): void
    {
        self::$currentId = $id;
    }

    public static function clear(): void
    {
        self::$currentId = null;
    }
}

// Usage in StructuredOutput
class StructuredOutput
{
    public function get(): object
    {
        $correlationId = CorrelationIdManager::generate();

        try {
            $metricId = $this->logPerformanceStart('structured_output_generation', [
                'correlation_id' => $correlationId,
                'response_class' => $this->responseClass,
            ]);

            $result = $this->executeRequest();

            $this->logPerformanceEnd($metricId, [
                'success' => true,
                'result_type' => get_class($result),
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Structured output generation failed', [
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            CorrelationIdManager::clear();
        }
    }
}
```

#### Event Filtering and Sampling

```php
<?php

namespace Cognesy\Instructor\Logging;

class LoggingFilter
{
    public function __construct(
        private array $excludedEvents = [],
        private array $levelOverrides = [],
        private float $samplingRate = 1.0,
        private int $maxEventsPerSecond = 100,
    ) {}

    public function shouldLog(Event $event): bool
    {
        // Check exclusions
        if (in_array($event::class, $this->excludedEvents)) {
            return false;
        }

        // Apply sampling
        if ($this->samplingRate < 1.0 && mt_rand() / mt_getrandmax() > $this->samplingRate) {
            return false;
        }

        // Rate limiting
        if ($this->isRateLimited($event)) {
            return false;
        }

        return true;
    }

    public function getLogLevel(Event $event): string
    {
        return $this->levelOverrides[$event::class] ?? $event->logLevel;
    }

    private function isRateLimited(Event $event): bool
    {
        // Implement sliding window rate limiting
        // Store in cache/memory with event class as key
        return false; // Simplified for example
    }
}
```

### 5. Developer Experience Improvements

#### Fluent Logging Configuration

```php
// Laravel example with fluent API
$user = StructuredOutput::create()
    ->withLogging()
        ->level('info')
        ->channel('instructor')
        ->metrics()
        ->context(['feature' => 'user_extraction'])
        ->correlationId('req_' . uniqid())
    ->withMessages("Extract user: Jason is 25 years old")
    ->withResponseClass(User::class)
    ->get();

// Symfony example
$user = $this->structuredOutput
    ->withLogging()
        ->level('debug')
        ->context(['controller' => self::class])
    ->withMessages($input)
    ->withResponseClass(User::class)
    ->get();
```

#### Debug Helpers

```php
<?php

namespace Cognesy\Instructor\Debug;

class LoggingDebug
{
    public static function dumpLastEvents(int $count = 10): void
    {
        // Access event history and dump recent events
        $events = EventHistory::getLast($count);

        foreach ($events as $event) {
            echo $event->asConsole() . PHP_EOL;
        }
    }

    public static function enableVerboseLogging(): void
    {
        // Set all Instructor services to DEBUG level
        app(StructuredOutput::class)->withLogLevel(LogLevel::DEBUG);
        app(Inference::class)->withLogLevel(LogLevel::DEBUG);
        app(Embeddings::class)->withLogLevel(LogLevel::DEBUG);
    }

    public static function logNextRequestOnly(): void
    {
        // Enable detailed logging for just the next request
        $token = uniqid('debug_');

        app(StructuredOutput::class)
            ->withLogContext(['debug_token' => $token])
            ->withLogging()
            ->withMetricsLogging();
    }
}
```

### 6. Migration Guide

#### For Laravel Projects

1. **Install and Configure**:
   ```bash
   composer require cognesy/instructor-php
   php artisan vendor:publish --tag=instructor-config
   ```

2. **Update Configuration**:
   ```php
   // config/instructor.php
   'logging' => [
       'auto_enable' => true,
       'channel' => 'instructor',
       'level' => 'info',
   ],
   ```

3. **Use Dependency Injection**:
   ```php
   class UserController extends Controller
   {
       public function __construct(
           private StructuredOutput $structuredOutput
       ) {}

       public function extract(Request $request): JsonResponse
       {
           // Logging is automatically configured
           $user = $this->structuredOutput
               ->withMessages($request->input('text'))
               ->withResponseClass(User::class)
               ->get();

           return response()->json($user);
       }
   }
   ```

#### For Symfony Projects

1. **Register Bundle**:
   ```php
   // config/bundles.php
   return [
       // ...
       Cognesy\Instructor\Symfony\InstructorBundle::class => ['all' => true],
   ];
   ```

2. **Configure Services**:
   ```yaml
   # config/packages/instructor.yaml
   instructor:
       logging:
           enabled: true
           logger: 'logger'
           level: 'info'
           metrics: true
   ```

3. **Use Service Injection**:
   ```php
   class UserController extends AbstractController
   {
       public function __construct(
           private StructuredOutput $structuredOutput
       ) {}

       public function extract(Request $request): JsonResponse
       {
           $user = $this->structuredOutput
               ->withMessages($request->getContent())
               ->withResponseClass(User::class)
               ->get();

           return $this->json($user);
       }
   }
   ```

## Implementation Plan

### Phase 1: Core Logging Infrastructure (Week 1-2)

1. **Create logging interfaces and traits**
   - `LoggerAware` interface
   - `HandlesLogging` trait
   - `EventLogConverter` class

2. **Enhance core classes**
   - Add `LoggerAware` to `StructuredOutput`, `Inference`, `Embeddings`
   - Implement automatic logger setup
   - Add fluent logging configuration

3. **Context providers**
   - Framework-agnostic `ContextProvider` interface
   - Basic implementation for request tracking

### Phase 2: Laravel Integration (Week 2-3)

1. **Enhanced ServiceProvider**
   - Auto-registration with logging
   - Configuration publishing
   - Context provider registration

2. **Laravel-specific features**
   - `LaravelContextProvider`
   - Custom formatter for Laravel logs
   - Artisan commands for log analysis

3. **Testing and documentation**
   - Integration tests
   - Usage examples
   - Migration guide

### Phase 3: Symfony Integration (Week 3-4)

1. **Symfony Bundle**
   - Bundle class and DI extension
   - Service definitions
   - Configuration system

2. **Symfony-specific features**
   - `SymfonyContextProvider`
   - Monolog integration
   - Developer toolbar integration

3. **Testing and documentation**
   - Bundle tests
   - Symfony examples
   - Bundle installation guide

### Phase 4: Advanced Features (Week 4-5)

1. **Performance and filtering**
   - Event sampling and rate limiting
   - Correlation ID support
   - Performance metrics collection

2. **Developer tools**
   - Debug helpers
   - Log analysis utilities
   - Development mode enhancements

3. **Documentation and examples**
   - Comprehensive guide
   - Real-world examples
   - Best practices documentation

## Expected Benefits

### For Developers

1. **Reduced Boilerplate**: Auto-configured logging eliminates manual setup
2. **Better Debugging**: Rich context and correlation IDs improve troubleshooting
3. **Performance Insights**: Built-in metrics help identify bottlenecks
4. **Framework Integration**: Native support for Laravel and Symfony patterns

### For Applications

1. **Observability**: Comprehensive logging of AI operations
2. **Monitoring**: Performance metrics and error tracking
3. **Compliance**: Structured logs suitable for audit trails
4. **Scalability**: Configurable filtering and sampling for high-volume scenarios

### For Framework Ecosystems

1. **Best Practices**: Standard patterns for AI library integration
2. **Consistency**: Unified logging approach across AI operations
3. **Extensibility**: Plugin architecture for custom logging needs
4. **Community**: Shared patterns and configurations

## Conclusion

This proposal builds upon Instructor-PHP's existing event-driven architecture to provide seamless framework integration while maintaining the library's flexibility and performance. The phased implementation approach ensures backward compatibility while delivering immediate value to developers using Laravel and Symfony.

The proposed changes align with framework conventions, provide superior developer experience, and establish Instructor-PHP as the gold standard for AI library integration in PHP applications.