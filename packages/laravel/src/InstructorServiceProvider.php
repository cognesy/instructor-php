<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Event;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\LaravelEventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Laravel\Console\InstructorInstallCommand;
use Cognesy\Instructor\Laravel\Console\InstructorTestCommand;
use Cognesy\Instructor\Laravel\Console\MakeResponseModelCommand;
use Cognesy\Instructor\Laravel\Support\LaravelConfigProvider;
use Cognesy\Instructor\Laravel\Testing\AgentCtrlFake;
use Cognesy\Instructor\Laravel\Testing\StructuredOutputFake;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Logging\Factories\LaravelLoggingFactory;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Illuminate\Contracts\Events\Dispatcher as LaravelDispatcher;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application as LaravelApplication;
use Illuminate\Http\Client\Factory as LaravelHttpFactory;
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
        $this->mergeConfigFrom(__DIR__ . '/../resources/config/instructor.php', 'instructor');

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
    }

    /**
     * Register the event dispatcher bridge.
     */
    protected function registerEventDispatcher(): void
    {
        $this->app->singleton(CanHandleEvents::class, function (Container $app) {
            $dispatchToLaravel = (bool) $this->configGet($app, 'instructor.events.dispatch_to_laravel', true);
            $bridgeEvents = $this->configGet($app, 'instructor.events.bridge_events', []);
            if (!is_array($bridgeEvents)) {
                $bridgeEvents = [];
            }

            return new LaravelEventDispatcher(
                $app->make(LaravelDispatcher::class),
                $dispatchToLaravel,
                $bridgeEvents,
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

            return (new HttpClientBuilder(events: $app->make(CanHandleEvents::class)))
                ->withConfig($httpConfig)
                ->withClientInstance('laravel', $app->make(LaravelHttpFactory::class))
                ->create();
        });
    }

    /**
     * Register the Inference service.
     */
    protected function registerInference(): void
    {
        $this->app->singleton(Inference::class, function (Container $app) {
            $runtime = InferenceRuntime::fromProvider(
                provider: LLMProvider::fromLLMConfig($this->resolveLLMConfig($app)),
                events: $app->make(CanHandleEvents::class),
                httpClient: $app->make(HttpClient::class),
            );
            $inference = new Inference($runtime);

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
            $runtime = EmbeddingsRuntime::fromProvider(
                provider: EmbeddingsProvider::fromEmbeddingsConfig($this->resolveEmbeddingsConfig($app)),
                events: $app->make(CanHandleEvents::class),
                httpClient: $app->make(HttpClient::class),
            );
            $embeddings = new Embeddings($runtime);

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
            $runtime = StructuredOutputRuntime::fromProvider(
                provider: LLMProvider::fromLLMConfig($this->resolveLLMConfig($app)),
                events: $app->make(CanHandleEvents::class),
                httpClient: $app->make(HttpClient::class),
                structuredConfig: $this->resolveStructuredOutputConfig($app),
            );
            $instructor = new StructuredOutput($runtime);

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
            return InferenceRuntime::fromProvider(
                provider: LLMProvider::fromLLMConfig($this->resolveLLMConfig($app)),
                events: $app->make(CanHandleEvents::class),
                httpClient: $app->make(HttpClient::class),
            );
        });

        $this->app->singleton(CanCreateEmbeddings::class, function (Container $app) {
            return EmbeddingsRuntime::fromProvider(
                provider: EmbeddingsProvider::fromEmbeddingsConfig($this->resolveEmbeddingsConfig($app)),
                events: $app->make(CanHandleEvents::class),
                httpClient: $app->make(HttpClient::class),
            );
        });

        $this->app->singleton(CanCreateStructuredOutput::class, function (Container $app) {
            return StructuredOutputRuntime::fromProvider(
                provider: LLMProvider::fromLLMConfig($this->resolveLLMConfig($app)),
                events: $app->make(CanHandleEvents::class),
                httpClient: $app->make(HttpClient::class),
                structuredConfig: $this->resolveStructuredOutputConfig($app),
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
                __DIR__ . '/../resources/config/instructor.php' => $this->app->configPath('instructor.php'),
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
     * Apply logging to a service.
     */
    protected function applyLogging(Container $app, object $service): void
    {
        if (!$app instanceof LaravelApplication) {
            return;
        }

        $preset = $this->configGet($app, 'instructor.logging.preset', 'production');
        $config = $this->configGet($app, 'instructor.logging', []);

        $pipeline = match ($preset) {
            'default' => LaravelLoggingFactory::defaultSetup($app),
            'production' => LaravelLoggingFactory::productionSetup($app),
            'custom' => LaravelLoggingFactory::create($app, $config),
            default => LaravelLoggingFactory::productionSetup($app),
        };

        if (method_exists($service, 'wiretap')) {
            $service->wiretap(static function (object $event) use ($pipeline): void {
                if (!$event instanceof Event) {
                    return;
                }

                $pipeline($event);
            });
            return;
        }

        if ($service instanceof Inference) {
            $app->make(CanHandleEvents::class)->wiretap(static function (object $event) use ($pipeline): void {
                if (!$event instanceof Event) {
                    return;
                }

                $pipeline($event);
            });
        }
    }

    private function configGet(Container $app, string $path, mixed $default = null): mixed {
        return $app->make('config')->get($path, $default);
    }

    private function resolveLLMConfig(Container $app): LLMConfig {
        $name = (string) $this->configGet($app, 'instructor.default', 'openai');
        $raw = $this->configGet($app, "instructor.connections.{$name}", []);
        $connection = is_array($raw) ? $raw : [];
        $driver = (string) ($connection['driver'] ?? $name ?: 'openai');
        $model = (string) ($connection['model'] ?? '');
        $apiUrl = (string) ($connection['api_url'] ?? '');
        $endpoint = (string) ($connection['endpoint'] ?? $this->defaultLlmEndpoint($driver, $model));

        $known = ['driver', 'api_url', 'api_key', 'endpoint', 'model', 'max_tokens', 'options'];
        $extraOptions = array_diff_key($connection, array_flip($known));
        $options = match (true) {
            isset($connection['options']) && is_array($connection['options']) => array_merge($extraOptions, $connection['options']),
            default => $extraOptions,
        };

        return LLMConfig::fromArray([
            'driver' => $driver,
            'apiUrl' => $apiUrl,
            'apiKey' => (string) ($connection['api_key'] ?? ''),
            'endpoint' => $endpoint,
            'model' => $model,
            'maxTokens' => (int) ($connection['max_tokens'] ?? 4096),
            'options' => $options,
        ]);
    }

    private function resolveEmbeddingsConfig(Container $app): EmbeddingsConfig {
        $name = (string) $this->configGet($app, 'instructor.embeddings.default', 'openai');
        $raw = $this->configGet($app, "instructor.embeddings.connections.{$name}", []);
        $connection = is_array($raw) ? $raw : [];
        $driver = (string) ($connection['driver'] ?? $name ?: 'openai');

        return EmbeddingsConfig::fromArray([
            'driver' => $driver,
            'apiUrl' => (string) ($connection['api_url'] ?? ''),
            'apiKey' => (string) ($connection['api_key'] ?? ''),
            'endpoint' => (string) ($connection['endpoint'] ?? '/embeddings'),
            'model' => (string) ($connection['model'] ?? ''),
            'dimensions' => (int) ($connection['dimensions'] ?? 0),
            'maxInputs' => (int) ($connection['max_inputs'] ?? 0),
        ]);
    }

    private function resolveStructuredOutputConfig(Container $app): StructuredOutputConfig {
        $data = [];
        $mode = $this->configGet($app, 'instructor.extraction.output_mode');
        if (is_string($mode) && $mode !== '') {
            $data['outputMode'] = $mode;
        }

        $maxRetries = $this->configGet($app, 'instructor.extraction.max_retries');
        if (is_int($maxRetries) || is_numeric($maxRetries)) {
            $data['maxRetries'] = (int) $maxRetries;
        }

        $retryPrompt = $this->configGet($app, 'instructor.extraction.retry_prompt');
        if (is_string($retryPrompt) && $retryPrompt !== '') {
            $data['retryPrompt'] = $retryPrompt;
        }

        return StructuredOutputConfig::fromArray($data);
    }

    private function defaultLlmEndpoint(string $driver, string $model): string {
        return match ($driver) {
            'anthropic' => '/messages',
            'gemini' => "/models/{$model}:generateContent",
            default => '/chat/completions',
        };
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
        ];
    }
}
