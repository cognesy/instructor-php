<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\LaravelEventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Laravel\Console\InstructorInstallCommand;
use Cognesy\Instructor\Laravel\Console\InstructorTestCommand;
use Cognesy\Instructor\Laravel\Console\MakeResponseModelCommand;
use Cognesy\Instructor\Laravel\Events\InstructorEventBridge;
use Cognesy\Instructor\Laravel\Support\LaravelConfigProvider;
use Cognesy\Instructor\Laravel\Testing\AgentCtrlFake;
use Cognesy\Instructor\Laravel\Testing\StructuredOutputFake;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Logging\Factories\LaravelLoggingFactory;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Inference\Inference;
use Illuminate\Contracts\Events\Dispatcher as LaravelDispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel Service Provider for Instructor PHP
 *
 * Provides seamless integration with Laravel, including:
 * - Container bindings for Inference, Embeddings, and StructuredOutput
 * - Laravel HTTP client integration (enables Http::fake() in tests)
 * - Event bridge to Laravel's event dispatcher
 * - Automatic logging with request context
 * - Artisan commands for development
 */
class InstructorServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/instructor.php', 'instructor');

        $this->registerEventDispatcher();
        $this->registerConfigProvider();
        $this->registerHttpClient();
        $this->registerInference();
        $this->registerEmbeddings();
        $this->registerStructuredOutput();
        $this->registerFakes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishConfiguration();
        $this->registerCommands();
        $this->configureLogging();
        $this->configureEventBridge();
    }

    /**
     * Register the event dispatcher bridge.
     */
    protected function registerEventDispatcher(): void
    {
        // Register our Laravel event dispatcher adapter
        $this->app->singleton(CanHandleEvents::class, function (Application $app) {
            return new LaravelEventDispatcher(
                $app->make(LaravelDispatcher::class)
            );
        });
    }

    /**
     * Register the config provider.
     */
    protected function registerConfigProvider(): void
    {
        $this->app->singleton(CanProvideConfig::class, function (Application $app) {
            return new LaravelConfigProvider($app);
        });
    }

    /**
     * Register the HTTP client with Laravel driver.
     */
    protected function registerHttpClient(): void
    {
        $this->app->singleton(HttpClient::class, function (Application $app) {
            $config = $app['config']['instructor.http'];

            $httpConfig = HttpClientConfig::fromArray([
                'driver' => $config['driver'] ?? 'laravel',
                'requestTimeout' => $config['timeout'] ?? 120,
                'connectTimeout' => $config['connect_timeout'] ?? 30,
            ]);

            return (new HttpClientBuilder(
                events: $app->make(CanHandleEvents::class),
                configProvider: $app->make(CanProvideConfig::class),
            ))
                ->withConfig($httpConfig)
                ->withClientInstance('laravel', new \Illuminate\Http\Client\Factory())
                ->create();
        });
    }

    /**
     * Register the Inference service.
     */
    protected function registerInference(): void
    {
        $this->app->singleton(Inference::class, function (Application $app) {
            $inference = new Inference(
                events: $app->make(CanHandleEvents::class),
                configProvider: $app->make(CanProvideConfig::class),
            );

            // Use Laravel HTTP client
            $inference->withHttpClient($app->make(HttpClient::class));

            // Apply default connection
            $default = $app['config']['instructor.default'];
            if ($default) {
                $inference->using($default);
            }

            // Apply logging if enabled
            if ($app['config']['instructor.logging.enabled']) {
                $this->applyLogging($app, $inference);
            }

            return $inference;
        });
    }

    /**
     * Register the Embeddings service.
     */
    protected function registerEmbeddings(): void
    {
        $this->app->singleton(Embeddings::class, function (Application $app) {
            $embeddings = new Embeddings(
                events: $app->make(CanHandleEvents::class),
                configProvider: $app->make(CanProvideConfig::class),
            );

            // Use Laravel HTTP client
            $embeddings->withHttpClient($app->make(HttpClient::class));

            // Apply default connection
            $default = $app['config']['instructor.embeddings.default'];
            if ($default) {
                $embeddings->using($default);
            }

            // Apply logging if enabled
            if ($app['config']['instructor.logging.enabled']) {
                $this->applyLogging($app, $embeddings);
            }

            return $embeddings;
        });
    }

    /**
     * Register the StructuredOutput service.
     */
    protected function registerStructuredOutput(): void
    {
        $this->app->bind(StructuredOutput::class, function (Application $app) {
            $instructor = new StructuredOutput(
                events: $app->make(CanHandleEvents::class),
                configProvider: $app->make(CanProvideConfig::class),
            );

            // Use Laravel HTTP client
            $instructor->withHttpClient($app->make(HttpClient::class));

            // Apply default connection
            $default = $app['config']['instructor.default'];
            if ($default) {
                $instructor->using($default);
            }

            // Apply extraction settings
            $maxRetries = $app['config']['instructor.extraction.max_retries'];
            if ($maxRetries !== null) {
                $instructor->withMaxRetries($maxRetries);
            }

            // Apply logging if enabled
            if ($app['config']['instructor.logging.enabled']) {
                $this->applyLogging($app, $instructor);
            }

            return $instructor;
        });
    }

    /**
     * Register testing fakes.
     */
    protected function registerFakes(): void
    {
        $this->app->bind(StructuredOutputFake::class, function (Application $app) {
            return new StructuredOutputFake();
        });

        $this->app->bind(AgentCtrlFake::class, function (Application $app) {
            return new AgentCtrlFake();
        });
    }

    /**
     * Publish configuration files.
     */
    protected function publishConfiguration(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/instructor.php' => config_path('instructor.php'),
            ], 'instructor-config');

            $this->publishes([
                __DIR__ . '/../resources/stubs' => base_path('stubs/instructor'),
            ], 'instructor-stubs');
        }
    }

    /**
     * Register Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstructorInstallCommand::class,
                InstructorTestCommand::class,
                MakeResponseModelCommand::class,
            ]);
        }
    }

    /**
     * Configure logging pipeline.
     */
    protected function configureLogging(): void
    {
        if (!$this->app['config']['instructor.logging.enabled']) {
            return;
        }

        // Logging is applied per-service in their registration
    }

    /**
     * Configure event bridge to Laravel events.
     */
    protected function configureEventBridge(): void
    {
        if (!$this->app['config']['instructor.events.dispatch_to_laravel']) {
            return;
        }

        $this->app->singleton(InstructorEventBridge::class, function (Application $app) {
            return new InstructorEventBridge(
                $app->make(LaravelDispatcher::class),
                $app['config']['instructor.events.bridge_events'] ?? []
            );
        });
    }

    /**
     * Apply logging to a service.
     */
    protected function applyLogging(Application $app, object $service): void
    {
        if (!method_exists($service, 'wiretap')) {
            return;
        }

        $preset = $app['config']['instructor.logging.preset'] ?? 'default';
        $config = $app['config']['instructor.logging'] ?? [];

        $pipeline = match ($preset) {
            'production' => LaravelLoggingFactory::productionSetup($app),
            'custom' => LaravelLoggingFactory::create($app, $config),
            default => LaravelLoggingFactory::defaultSetup($app),
        };

        $service->wiretap($pipeline);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            CanHandleEvents::class,
            CanProvideConfig::class,
            HttpClient::class,
            Inference::class,
            Embeddings::class,
            StructuredOutput::class,
            StructuredOutputFake::class,
            AgentCtrlFake::class,
            InstructorEventBridge::class,
        ];
    }
}
