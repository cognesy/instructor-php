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
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Logging\Factories\LaravelLoggingFactory;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Illuminate\Contracts\Events\Dispatcher as LaravelDispatcher;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application as LaravelApplication;
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
        $this->registerRuntimeCreators();
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
        $this->app->singleton(CanHandleEvents::class, function (Container $app) {
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
        $this->app->singleton(CanProvideConfig::class, function (Container $app) {
            return new LaravelConfigProvider($app);
        });
    }

    /**
     * Register the HTTP client with Laravel driver.
     */
    protected function registerHttpClient(): void
    {
        $this->app->singleton(HttpClient::class, function (Container $app) {
            $config = $this->configGet($app, 'instructor.http', []);

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
        $this->app->singleton(Inference::class, function (Container $app) {
            $inference = new Inference(
                events: $app->make(CanHandleEvents::class),
                configProvider: $app->make(CanProvideConfig::class),
            );

            // Use Laravel HTTP client
            $inference->withHttpClient($app->make(HttpClient::class));

            // Apply default connection
            $default = $this->configGet($app, 'instructor.default');
            if ($default) {
                $inference->using($default);
            }

            // Apply logging if enabled
            if ($this->configGet($app, 'instructor.logging.enabled', true)) {
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
        $this->app->singleton(Embeddings::class, function (Container $app) {
            $embeddings = new Embeddings(
                events: $app->make(CanHandleEvents::class),
                configProvider: $app->make(CanProvideConfig::class),
            );

            // Use Laravel HTTP client
            $embeddings->withHttpClient($app->make(HttpClient::class));

            // Apply default connection
            $default = $this->configGet($app, 'instructor.embeddings.default');
            if ($default) {
                $embeddings->using($default);
            }

            // Apply logging if enabled
            if ($this->configGet($app, 'instructor.logging.enabled', true)) {
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
        $this->app->bind(StructuredOutput::class, function (Container $app) {
            $instructor = new StructuredOutput(
                events: $app->make(CanHandleEvents::class),
                configProvider: $app->make(CanProvideConfig::class),
            );

            // Use Laravel HTTP client
            $instructor->withHttpClient($app->make(HttpClient::class));

            // Apply default connection
            $default = $this->configGet($app, 'instructor.default');
            if ($default) {
                $instructor->using($default);
            }

            // Apply extraction settings
            $maxRetries = $this->configGet($app, 'instructor.extraction.max_retries');
            if ($maxRetries !== null) {
                $instructor->withMaxRetries($maxRetries);
            }

            // Apply logging if enabled
            if ($this->configGet($app, 'instructor.logging.enabled', true)) {
                $this->applyLogging($app, $instructor);
            }

            return $instructor;
        });
    }

    /**
     * Register runtime-first creator contracts.
     */
    protected function registerRuntimeCreators(): void
    {
        $this->app->singleton(CanCreateInference::class, function (Container $app) {
            $provider = LLMProvider::new(
                configProvider: $app->make(CanProvideConfig::class),
            );
            $default = $this->configGet($app, 'instructor.default');
            if ($default) {
                $provider = $provider->withLLMPreset($default);
            }
            return InferenceRuntime::fromProvider(
                provider: $provider,
                events: $app->make(CanHandleEvents::class),
                httpClient: $app->make(HttpClient::class),
            );
        });

        $this->app->singleton(CanCreateEmbeddings::class, function (Container $app) {
            $provider = EmbeddingsProvider::new(
                configProvider: $app->make(CanProvideConfig::class),
                events: $app->make(CanHandleEvents::class),
            );
            $default = $this->configGet($app, 'instructor.embeddings.default');
            if ($default) {
                $provider = $provider->withPreset($default);
            }
            return EmbeddingsRuntime::fromProvider(
                provider: $provider,
                events: $app->make(CanHandleEvents::class),
                httpClient: $app->make(HttpClient::class),
            );
        });

        $this->app->singleton(CanCreateStructuredOutput::class, function (Container $app) {
            $provider = LLMProvider::new(
                configProvider: $app->make(CanProvideConfig::class),
            );
            $default = $this->configGet($app, 'instructor.default');
            if ($default) {
                $provider = $provider->withLLMPreset($default);
            }
            $configBuilder = new StructuredOutputConfigBuilder(
                configProvider: $app->make(CanProvideConfig::class),
            );
            $maxRetries = $this->configGet($app, 'instructor.extraction.max_retries');
            if ($maxRetries !== null) {
                $configBuilder = $configBuilder->withMaxRetries($maxRetries);
            }
            return StructuredOutputRuntime::fromProvider(
                provider: $provider,
                events: $app->make(CanHandleEvents::class),
                httpClient: $app->make(HttpClient::class),
                structuredConfig: $configBuilder->create(),
            );
        });
    }

    /**
     * Register testing fakes.
     */
    protected function registerFakes(): void
    {
        $this->app->bind(StructuredOutputFake::class, function (Container $app) {
            return new StructuredOutputFake();
        });

        $this->app->bind(AgentCtrlFake::class, function (Container $app) {
            return new AgentCtrlFake();
        });
    }

    /**
     * Publish configuration files.
     */
    protected function publishConfiguration(): void
    {
        if (!$this->app instanceof LaravelApplication) {
            return;
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/instructor.php' => $this->app->configPath('instructor.php'),
            ], 'instructor-config');

            $this->publishes([
                __DIR__ . '/../resources/stubs' => $this->app->basePath('stubs/instructor'),
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
        if (!$this->configGet($this->app, 'instructor.logging.enabled', true)) {
            return;
        }

        // Logging is applied per-service in their registration
    }

    /**
     * Configure event bridge to Laravel events.
     */
    protected function configureEventBridge(): void
    {
        if (!$this->configGet($this->app, 'instructor.events.dispatch_to_laravel', true)) {
            return;
        }

        $this->app->singleton(InstructorEventBridge::class, function (Container $app) {
            return new InstructorEventBridge(
                $app->make(LaravelDispatcher::class),
                $this->configGet($app, 'instructor.events.bridge_events', [])
            );
        });
    }

    /**
     * Apply logging to a service.
     */
    protected function applyLogging(Container $app, object $service): void
    {
        if (!method_exists($service, 'wiretap')) {
            return;
        }
        if (!$app instanceof LaravelApplication) {
            return;
        }

        $preset = $this->configGet($app, 'instructor.logging.preset', 'default');
        $config = $this->configGet($app, 'instructor.logging', []);

        $pipeline = match ($preset) {
            'production' => LaravelLoggingFactory::productionSetup($app),
            'custom' => LaravelLoggingFactory::create($app, $config),
            default => LaravelLoggingFactory::defaultSetup($app),
        };

        $service->wiretap($pipeline);
    }

    private function configGet(Container $app, string $path, mixed $default = null): mixed {
        return $app->make('config')->get($path, $default);
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
            CanCreateInference::class,
            CanCreateEmbeddings::class,
            CanCreateStructuredOutput::class,
            StructuredOutputFake::class,
            AgentCtrlFake::class,
            InstructorEventBridge::class,
        ];
    }
}
