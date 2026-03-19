<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel;

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\CanManageAgentCapabilities;
use Cognesy\Agents\Capability\StructuredOutput\CanManageSchemas;
use Cognesy\Agents\Capability\StructuredOutput\SchemaDefinition;
use Cognesy\Agents\Capability\StructuredOutput\SchemaRegistry;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Broadcasting\BroadcastConfig;
use Cognesy\Agents\Broadcasting\CanBroadcastAgentEvents;
use Cognesy\Agents\Telemetry\AgentsTelemetryProjector;
use Cognesy\Agents\Session\Contracts\CanManageAgentSessions;
use Cognesy\Agents\Session\Contracts\CanStoreSessions;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\AgentCtrl\Telemetry\AgentCtrlTelemetryProjector;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Tool\Contracts\CanManageTools;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Event;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Telemetry\HttpClientTelemetryProjector;
use Cognesy\Instructor\Laravel\Agents\AgentRegistryTags;
use Cognesy\Instructor\Laravel\Agents\Broadcasting\LaravelAgentBroadcasting;
use Cognesy\Instructor\Laravel\Agents\Broadcasting\LaravelAgentEventTransport;
use Cognesy\Instructor\Laravel\Agents\Broadcasting\NullAgentEventTransport;
use Cognesy\Instructor\Laravel\Agents\Session\DatabaseSessionStore;
use Cognesy\Instructor\Laravel\Agents\SchemaRegistration;
use Cognesy\Instructor\Laravel\HttpClient\LaravelDriver;
use Cognesy\Instructor\Laravel\Console\InstructorInstallCommand;
use Cognesy\Instructor\Laravel\Console\InstructorTestCommand;
use Cognesy\Instructor\Laravel\Console\MakeResponseModelCommand;
use Cognesy\Instructor\Laravel\Events\LaravelEventDispatcher;
use Cognesy\Instructor\Laravel\Logging\LaravelLoggingFactory;
use Cognesy\Instructor\Laravel\Support\LaravelConfigProvider;
use Cognesy\Instructor\Laravel\Telemetry\NullTelemetryExporter;
use Cognesy\Instructor\Laravel\Telemetry\TelemetryBridgeState;
use Cognesy\Instructor\Laravel\Testing\AgentCtrlFake;
use Cognesy\Instructor\Laravel\Testing\NativeAgentTesting;
use Cognesy\Instructor\Laravel\Testing\StructuredOutputFake;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Telemetry\InstructorTelemetryProjector;
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
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseConfig;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseExporter;
use Cognesy\Telemetry\Adapters\Logfire\LogfireConfig;
use Cognesy\Telemetry\Adapters\Logfire\LogfireExporter;
use Cognesy\Telemetry\Adapters\OTel\OtelConfig;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Adapters\OTel\OtelHttpTransport;
use Cognesy\Telemetry\Application\Exporter\CompositeTelemetryExporter;
use Cognesy\Telemetry\Application\Projector\CanProjectTelemetry;
use Cognesy\Telemetry\Application\Projector\CompositeTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Contract\CanFlushTelemetry;
use Cognesy\Telemetry\Domain\Contract\CanShutdownTelemetry;
use Illuminate\Contracts\Events\Dispatcher as LaravelDispatcher;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application as LaravelApplication;
use Illuminate\Http\Client\Factory as LaravelHttpFactory;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

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

        $this->registerFoundationServices();
        $this->registerTransportServices();
        $this->registerPrimitiveServices();
        $this->registerCodeAgentServices();
        $this->registerNativeAgentServices();
        $this->registerObservabilityServices();
        $this->registerTestingServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishResources();
        $this->registerCommands();
        $this->bootObservability();
    }

    /**
     * Register the common package foundation.
     */
    protected function registerFoundationServices(): void
    {
        $this->registerEventDispatcher();
        $this->registerConfigProvider();
    }

    /**
     * Register shared transport services.
     */
    protected function registerTransportServices(): void
    {
        $this->registerHttpClient();
    }

    /**
     * Register Instructor and Polyglot runtime services.
     */
    protected function registerPrimitiveServices(): void
    {
        $this->registerInference();
        $this->registerEmbeddings();
        $this->registerStructuredOutput();
        $this->registerRuntimeCreators();
    }

    /**
     * Register CLI code-agent integration seams.
     */
    protected function registerCodeAgentServices(): void
    {
        // AgentCtrl is facade-driven today, but this seam keeps code-agent wiring separate
        // from native Cognesy\Agents registration.
    }

    /**
     * Register native agent integration seams.
     */
    protected function registerNativeAgentServices(): void
    {
        $this->registerNativeAgentRegistries();
        $this->registerNativeAgentRuntime();
        $this->registerNativeAgentBroadcasting();
    }

    /**
     * Register observability-related integration seams.
     */
    protected function registerObservabilityServices(): void
    {
        $this->registerTelemetryServices();
    }

    /**
     * Register testing support services.
     */
    protected function registerTestingServices(): void
    {
        $this->registerFakes();
        $this->registerNativeAgentTesting();
    }

    /**
     * Register telemetry integration seams.
     */
    protected function registerTelemetryServices(): void
    {
        if (!$this->app->bound(TraceRegistry::class)) {
            $this->app->singleton(TraceRegistry::class, fn () => new TraceRegistry());
        }

        if (!$this->app->bound(CanExportObservations::class)) {
            $this->app->singleton(CanExportObservations::class, fn (Container $app) => $this->makeTelemetryExporter($app));
        }

        if (!$this->app->bound(Telemetry::class)) {
            $this->app->singleton(Telemetry::class, fn (Container $app) => new Telemetry(
                registry: $app->make(TraceRegistry::class),
                exporter: $app->make(CanExportObservations::class),
            ));
        }

        if (!$this->app->bound(CanFlushTelemetry::class)) {
            $this->app->singleton(CanFlushTelemetry::class, fn (Container $app) => $app->make(Telemetry::class));
        }

        if (!$this->app->bound(CanShutdownTelemetry::class)) {
            $this->app->singleton(CanShutdownTelemetry::class, fn (Container $app) => $app->make(Telemetry::class));
        }

        if (!$this->app->bound(CanProjectTelemetry::class)) {
            $this->app->singleton(CanProjectTelemetry::class, fn (Container $app) => new CompositeTelemetryProjector(
                $this->makeTelemetryProjectors($app),
            ));
        }

        if (!$this->app->bound(RuntimeEventBridge::class)) {
            $this->app->singleton(RuntimeEventBridge::class, fn (Container $app) => new RuntimeEventBridge(
                projector: $app->make(CanProjectTelemetry::class),
            ));
        }

        if (!$this->app->bound(TelemetryBridgeState::class)) {
            $this->app->singleton(TelemetryBridgeState::class, fn () => new TelemetryBridgeState());
        }

        $this->app->afterResolving(CanHandleEvents::class, function (CanHandleEvents $events, Container $app): void {
            $this->attachTelemetryBridge($app, $events);
        });

        if ($this->app->resolved(CanHandleEvents::class)) {
            $this->attachTelemetryBridge($this->app, $this->app->make(CanHandleEvents::class));
        }
    }

    /**
     * Register native agent registries.
     */
    protected function registerNativeAgentRegistries(): void
    {
        $this->app->singleton(AgentDefinitionRegistry::class, fn (Container $app) => $this->makeDefinitionRegistry($app));
        $this->app->singleton(CanManageAgentDefinitions::class, fn (Container $app) => $app->make(AgentDefinitionRegistry::class));

        $this->app->singleton(ToolRegistry::class, fn (Container $app) => $this->makeToolRegistry($app));
        $this->app->singleton(CanManageTools::class, fn (Container $app) => $app->make(ToolRegistry::class));

        $this->app->singleton(AgentCapabilityRegistry::class, fn (Container $app) => $this->makeCapabilityRegistry($app));
        $this->app->singleton(CanManageAgentCapabilities::class, fn (Container $app) => $app->make(AgentCapabilityRegistry::class));

        $this->app->singleton(SchemaRegistry::class, fn (Container $app) => $this->makeSchemaRegistry($app));
        $this->app->singleton(CanManageSchemas::class, fn (Container $app) => $app->make(SchemaRegistry::class));
    }

    /**
     * Register the default native agent runtime graph.
     */
    protected function registerNativeAgentRuntime(): void
    {
        $this->app->singleton(InMemorySessionStore::class, fn () => new InMemorySessionStore());
        $this->app->singleton(DatabaseSessionStore::class, fn (Container $app) => new DatabaseSessionStore(
            database: $app->make(\Illuminate\Database\ConnectionResolverInterface::class),
        ));
        $this->app->singleton(CanStoreSessions::class, fn (Container $app) => match ((string) $this->configGet($app, 'instructor.agents.session_store', 'memory')) {
            'database' => $app->make(DatabaseSessionStore::class),
            default => $app->make(InMemorySessionStore::class),
        });

        $this->app->singleton(SessionRepository::class, fn (Container $app) => new SessionRepository(
            store: $app->make(CanStoreSessions::class),
        ));

        $this->app->singleton(DefinitionLoopFactory::class, fn (Container $app) => new DefinitionLoopFactory(
            capabilities: $app->make(CanManageAgentCapabilities::class),
            tools: $app->make(CanManageTools::class),
            events: $app->make(CanHandleEvents::class),
        ));
        $this->app->singleton(CanInstantiateAgentLoop::class, fn (Container $app) => $app->make(DefinitionLoopFactory::class));

        $this->app->singleton(SessionRuntime::class, fn (Container $app) => new SessionRuntime(
            sessions: $app->make(SessionRepository::class),
            events: $app->make(CanHandleEvents::class),
        ));
        $this->app->singleton(CanManageAgentSessions::class, fn (Container $app) => $app->make(SessionRuntime::class));
    }

    /**
     * Register native agent broadcasting services.
     */
    protected function registerNativeAgentBroadcasting(): void
    {
        $this->app->singleton(BroadcastConfig::class, fn (Container $app) => $this->makeNativeAgentBroadcastConfig($app));
        $this->app->singleton(NullAgentEventTransport::class, fn () => new NullAgentEventTransport());
        $this->app->singleton(LaravelAgentEventTransport::class, fn (Container $app) => new LaravelAgentEventTransport(
            broadcasting: $app->make(BroadcastingFactory::class),
            connection: $this->nullableString($this->configGet($app, 'instructor.agents.broadcasting.connection')),
            eventName: (string) $this->configGet($app, 'instructor.agents.broadcasting.event_name', 'instructor.agent.event'),
        ));
        $this->app->singleton(CanBroadcastAgentEvents::class, fn (Container $app) => match (true) {
            !$this->configGet($app, 'instructor.agents.broadcasting.enabled', false) => $app->make(NullAgentEventTransport::class),
            !$app->bound(BroadcastingFactory::class) => $app->make(NullAgentEventTransport::class),
            default => $app->make(LaravelAgentEventTransport::class),
        });
        $this->app->singleton(LaravelAgentBroadcasting::class, fn (Container $app) => new LaravelAgentBroadcasting(
            transport: $app->make(CanBroadcastAgentEvents::class),
            config: $app->make(BroadcastConfig::class),
        ));
    }

    /**
     * Publish package resources.
     */
    protected function publishResources(): void
    {
        $this->loadPackageMigrations();
        $this->publishConfiguration();
        $this->publishMigrations();
    }

    /**
     * Bootstrap observability services.
     */
    protected function bootObservability(): void
    {
        $this->configureLogging();
        $this->configureTelemetryLifecycle();
    }

    /**
     * Load package migrations into Laravel.
     */
    protected function loadPackageMigrations(): void
    {
        if (!$this->app instanceof LaravelApplication) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
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
                ->withDriver(new LaravelDriver(
                    config: $httpConfig,
                    events: $app->make(CanHandleEvents::class),
                    clientInstance: $app->make(LaravelHttpFactory::class),
                ))
                ->create();
        });

        $this->app->singleton(CanSendHttpRequests::class, function (Container $app) {
            return $app->make(HttpClient::class);
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
                httpClient: $app->make(CanSendHttpRequests::class),
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
                httpClient: $app->make(CanSendHttpRequests::class),
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
                httpClient: $app->make(CanSendHttpRequests::class),
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
                httpClient: $app->make(CanSendHttpRequests::class),
            );
        });

        $this->app->singleton(CanCreateEmbeddings::class, function (Container $app) {
            return EmbeddingsRuntime::fromProvider(
                provider: EmbeddingsProvider::fromEmbeddingsConfig($this->resolveEmbeddingsConfig($app)),
                events: $app->make(CanHandleEvents::class),
                httpClient: $app->make(CanSendHttpRequests::class),
            );
        });

        $this->app->singleton(CanCreateStructuredOutput::class, function (Container $app) {
            return StructuredOutputRuntime::fromProvider(
                provider: LLMProvider::fromLLMConfig($this->resolveLLMConfig($app)),
                events: $app->make(CanHandleEvents::class),
                httpClient: $app->make(CanSendHttpRequests::class),
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
     * Register native-agent testing helpers.
     */
    protected function registerNativeAgentTesting(): void
    {
        $this->app->singleton(NativeAgentTesting::class, fn () => new NativeAgentTesting($this->app));
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
     * Publish package migrations.
     */
    protected function publishMigrations(): void
    {
        if (!$this->app instanceof LaravelApplication) {
            return;
        }

        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../database/migrations/2026_03_19_000000_create_instructor_agent_sessions_table.php'
                => $this->app->databasePath('migrations/2026_03_19_000000_create_instructor_agent_sessions_table.php'),
        ], 'instructor-migrations');
    }

    /**
     * Register Artisan commands.
     */
    protected function registerCommands(): void
    {
        if (!$this->app instanceof LaravelApplication) {
            return;
        }

        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstructorInstallCommand::class,
            InstructorTestCommand::class,
            MakeResponseModelCommand::class,
        ]);
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
     * Configure telemetry lifecycle hooks.
     */
    protected function configureTelemetryLifecycle(): void
    {
        if (!$this->telemetryEnabled($this->app)) {
            return;
        }

        if (!$this->app instanceof LaravelApplication) {
            return;
        }

        $this->app->terminating(function (): void {
            $this->app->make(CanFlushTelemetry::class)->flush();
            $this->app->make(CanShutdownTelemetry::class)->shutdown();
        });
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

    private function makeNativeAgentBroadcastConfig(Container $app): BroadcastConfig
    {
        $config = $this->configGet($app, 'instructor.agents.broadcasting', []);
        $data = is_array($config) ? $config : [];
        $preset = is_string($data['preset'] ?? null) ? $data['preset'] : 'standard';
        $base = match ($preset) {
            'minimal' => BroadcastConfig::minimal(),
            'debug' => BroadcastConfig::debug(),
            default => BroadcastConfig::standard(),
        };

        return new BroadcastConfig(
            includeStreamChunks: (bool) ($data['include_stream_chunks'] ?? $base->includeStreamChunks),
            includeContinuationTrace: (bool) ($data['include_continuation_trace'] ?? $base->includeContinuationTrace),
            includeToolArgs: (bool) ($data['include_tool_args'] ?? $base->includeToolArgs),
            maxArgLength: (int) ($data['max_arg_length'] ?? $base->maxArgLength),
            autoStatusTracking: (bool) ($data['auto_status_tracking'] ?? $base->autoStatusTracking),
        );
    }

    private function makeTelemetryExporter(Container $app): CanExportObservations
    {
        $driver = $this->configGet($app, 'instructor.telemetry.driver', 'null');

        return match (true) {
            is_string($driver) && $driver !== '' => $this->makeTelemetryExporterForDriver($app, $driver, true),
            is_array($driver) => new CompositeTelemetryExporter($this->makeCompositeTelemetryExporters($app, $driver)),
            default => new NullTelemetryExporter(),
        };
    }

    private function makeTelemetryExporterForDriver(Container $app, string $driver, bool $allowComposite): CanExportObservations
    {
        return match ($driver) {
            'otel' => $this->makeOtelExporter($app),
            'langfuse' => $this->makeLangfuseExporter($app),
            'logfire' => $this->makeLogfireExporter($app),
            'composite' => match ($allowComposite) {
                true => new CompositeTelemetryExporter(
                    $this->makeCompositeTelemetryExporters(
                        $app,
                        $this->configGet($app, 'instructor.telemetry.drivers.composite.exporters', []),
                    ),
                ),
                default => new NullTelemetryExporter(),
            },
            default => new NullTelemetryExporter(),
        };
    }

    /** @param array<array-key, mixed> $drivers */
    private function makeCompositeTelemetryExporters(Container $app, array $drivers): array
    {
        $exporters = [];

        foreach ($drivers as $driver) {
            if (!is_string($driver) || $driver === '') {
                continue;
            }

            $exporters[] = $this->makeTelemetryExporterForDriver($app, $driver, false);
        }

        return $exporters;
    }

    /** @return list<CanProjectTelemetry> */
    private function makeTelemetryProjectors(Container $app): array
    {
        $projectors = [];

        foreach ($this->enabledTelemetryProjectorKeys($app) as $key) {
            $projector = $this->makeTelemetryProjector($app, $key);
            if ($projector === null) {
                continue;
            }

            $projectors[] = $projector;
        }

        return $projectors;
    }

    /** @return list<string> */
    private function enabledTelemetryProjectorKeys(Container $app): array
    {
        $config = $this->configGet($app, 'instructor.telemetry.projectors', []);

        if (!is_array($config)) {
            return [];
        }

        $enabled = [];
        foreach ($config as $key => $value) {
            if (is_int($key) && is_string($value) && $value !== '') {
                $enabled[] = $value;
                continue;
            }

            if (is_string($key) && $value) {
                $enabled[] = $key;
            }
        }

        return $enabled;
    }

    private function makeTelemetryProjector(Container $app, string $key): ?CanProjectTelemetry
    {
        return match ($key) {
            'instructor' => new InstructorTelemetryProjector($app->make(Telemetry::class)),
            'polyglot' => new PolyglotTelemetryProjector($app->make(Telemetry::class)),
            'http' => new HttpClientTelemetryProjector(
                telemetry: $app->make(Telemetry::class),
                captureStreamingChunks: (bool) $this->configGet($app, 'instructor.telemetry.http.capture_streaming_chunks', false),
            ),
            'agent_ctrl' => new AgentCtrlTelemetryProjector($app->make(Telemetry::class)),
            'agents' => new AgentsTelemetryProjector($app->make(Telemetry::class)),
            default => null,
        };
    }

    private function makeOtelExporter(Container $app): CanExportObservations
    {
        $endpoint = (string) $this->configGet($app, 'instructor.telemetry.drivers.otel.endpoint', '');

        if ($endpoint === '') {
            return new NullTelemetryExporter();
        }

        return new OtelExporter(
            transport: new OtelHttpTransport(new OtelConfig(
                endpoint: $endpoint,
                serviceName: (string) $this->configGet($app, 'instructor.telemetry.service_name', 'laravel'),
                headers: $this->stringMap($this->configGet($app, 'instructor.telemetry.drivers.otel.headers', [])),
            )),
        );
    }

    private function makeLangfuseExporter(Container $app): CanExportObservations
    {
        $baseUrl = (string) $this->configGet($app, 'instructor.telemetry.drivers.langfuse.host', '');
        $publicKey = (string) $this->configGet($app, 'instructor.telemetry.drivers.langfuse.public_key', '');
        $secretKey = (string) $this->configGet($app, 'instructor.telemetry.drivers.langfuse.secret_key', '');

        if ($baseUrl === '' || $publicKey === '' || $secretKey === '') {
            return new NullTelemetryExporter();
        }

        return new LangfuseExporter(config: new LangfuseConfig(
            baseUrl: $baseUrl,
            publicKey: $publicKey,
            secretKey: $secretKey,
        ));
    }

    private function makeLogfireExporter(Container $app): CanExportObservations
    {
        $token = (string) $this->configGet($app, 'instructor.telemetry.drivers.logfire.write_token', '');
        $endpoint = (string) $this->configGet($app, 'instructor.telemetry.drivers.logfire.endpoint', '');

        if ($token === '' || $endpoint === '') {
            return new NullTelemetryExporter();
        }

        $headers = $this->stringMap($this->configGet($app, 'instructor.telemetry.drivers.logfire.headers', []));
        $headers['Authorization'] = $token;

        return new LogfireExporter(new LogfireConfig(
            endpoint: $endpoint,
            serviceName: (string) $this->configGet($app, 'instructor.telemetry.service_name', 'laravel'),
            headers: $headers,
        ));
    }

    private function attachTelemetryBridge(Container $app, CanHandleEvents $events): void
    {
        if (!$this->telemetryEnabled($app)) {
            return;
        }

        $state = $app->make(TelemetryBridgeState::class);
        if ($state->attached()) {
            return;
        }

        $app->make(RuntimeEventBridge::class)->attachTo($events);
        $state->markAttached();
    }

    private function telemetryEnabled(Container $app): bool
    {
        return (bool) $this->configGet($app, 'instructor.telemetry.enabled', false);
    }

    /** @return array<string, string> */
    private function stringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $resolved = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || !is_scalar($item)) {
                continue;
            }

            $resolved[$key] = (string) $item;
        }

        return $resolved;
    }

    private function nullableString(mixed $value): ?string
    {
        return match (true) {
            is_string($value) && $value !== '' => $value,
            default => null,
        };
    }

    private function makeDefinitionRegistry(Container $app): AgentDefinitionRegistry {
        $registry = new AgentDefinitionRegistry();
        $this->registerConfiguredDefinitions($app, $registry);
        $this->registerTaggedDefinitions($app, $registry);
        return $registry;
    }

    private function makeToolRegistry(Container $app): ToolRegistry {
        $registry = new ToolRegistry();
        $this->registerConfiguredTools($app, $registry);
        $this->registerTaggedTools($app, $registry);
        return $registry;
    }

    private function makeCapabilityRegistry(Container $app): AgentCapabilityRegistry {
        $registry = new AgentCapabilityRegistry();
        $this->registerConfiguredCapabilities($app, $registry);
        $this->registerTaggedCapabilities($app, $registry);
        return $registry;
    }

    private function makeSchemaRegistry(Container $app): SchemaRegistry {
        $registry = new SchemaRegistry();
        $this->registerConfiguredSchemas($app, $registry);
        $this->registerTaggedSchemas($app, $registry);
        return $registry;
    }

    private function registerConfiguredDefinitions(Container $app, AgentDefinitionRegistry $registry): void {
        $paths = $this->configGet($app, 'instructor.agents.definitions', []);
        if (!is_array($paths)) {
            return;
        }

        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            $this->loadDefinitionPath($registry, $path);
        }
    }

    private function registerTaggedDefinitions(Container $app, AgentDefinitionRegistry $registry): void {
        foreach ($app->tagged(AgentRegistryTags::DEFINITIONS) as $definition) {
            if (!$definition instanceof AgentDefinition) {
                throw new InvalidArgumentException('Tagged native agent definitions must resolve to AgentDefinition instances.');
            }
            $registry->register($definition);
        }
    }

    private function loadDefinitionPath(AgentDefinitionRegistry $registry, string $path): void {
        if (is_dir($path)) {
            $registry->loadFromDirectory($path, false);
            return;
        }

        if (is_file($path)) {
            $registry->loadFromFile($path);
            return;
        }

        throw new InvalidArgumentException("Configured native agent definition path does not exist: {$path}");
    }

    private function registerConfiguredTools(Container $app, ToolRegistry $registry): void {
        $tools = $this->configGet($app, 'instructor.agents.tools', []);
        if (!is_array($tools)) {
            return;
        }

        foreach ($tools as $toolClass) {
            if (!is_string($toolClass) || $toolClass === '') {
                continue;
            }
            $registry->register($this->resolveTool($app->make($toolClass)));
        }
    }

    private function registerTaggedTools(Container $app, ToolRegistry $registry): void {
        foreach ($app->tagged(AgentRegistryTags::TOOLS) as $tool) {
            $registry->register($this->resolveTool($tool));
        }
    }

    private function resolveTool(mixed $tool): ToolInterface {
        if ($tool instanceof ToolInterface) {
            return $tool;
        }

        throw new InvalidArgumentException('Native agent tools must resolve to ToolInterface instances.');
    }

    private function registerConfiguredCapabilities(Container $app, AgentCapabilityRegistry $registry): void {
        $capabilities = $this->configGet($app, 'instructor.agents.capabilities', []);
        if (!is_array($capabilities)) {
            return;
        }

        foreach ($capabilities as $name => $capabilityClass) {
            if (!is_string($capabilityClass) || $capabilityClass === '') {
                continue;
            }

            $capability = $this->resolveCapability($app->make($capabilityClass));
            $registry->register($this->capabilityName($name, $capability), $capability);
        }
    }

    private function registerTaggedCapabilities(Container $app, AgentCapabilityRegistry $registry): void {
        foreach ($app->tagged(AgentRegistryTags::CAPABILITIES) as $capability) {
            $resolved = $this->resolveCapability($capability);
            $registry->register($resolved::capabilityName(), $resolved);
        }
    }

    private function resolveCapability(mixed $capability): CanProvideAgentCapability {
        if ($capability instanceof CanProvideAgentCapability) {
            return $capability;
        }

        throw new InvalidArgumentException('Native agent capabilities must resolve to CanProvideAgentCapability instances.');
    }

    private function capabilityName(int|string $name, CanProvideAgentCapability $capability): string {
        return match (true) {
            is_string($name) && $name !== '' => $name,
            default => $capability::capabilityName(),
        };
    }

    private function registerConfiguredSchemas(Container $app, SchemaRegistry $registry): void {
        $schemas = $this->configGet($app, 'instructor.agents.schemas', []);
        if (!is_array($schemas)) {
            return;
        }

        foreach ($schemas as $name => $schema) {
            if (!is_string($name) || $name === '') {
                continue;
            }

            $registry->register($name, $this->normalizeSchema($schema));
        }
    }

    private function registerTaggedSchemas(Container $app, SchemaRegistry $registry): void {
        foreach ($app->tagged(AgentRegistryTags::SCHEMAS) as $registration) {
            if (!$registration instanceof SchemaRegistration) {
                throw new InvalidArgumentException('Tagged native agent schemas must resolve to SchemaRegistration instances.');
            }

            $registry->register($registration->name, $registration->schema);
        }
    }

    private function normalizeSchema(mixed $schema): string|SchemaDefinition {
        return match (true) {
            is_string($schema) && $schema !== '' => $schema,
            $schema instanceof SchemaDefinition => $schema,
            is_array($schema) => $this->schemaFromArray($schema),
            default => throw new InvalidArgumentException('Configured native agent schemas must be class strings, arrays, or SchemaDefinition instances.'),
        };
    }

    /** @param array<string, mixed> $schema */
    private function schemaFromArray(array $schema): SchemaDefinition {
        $class = $schema['class'] ?? null;
        if (!is_string($class) || $class === '') {
            throw new InvalidArgumentException('Configured schema arrays must define a non-empty class.');
        }

        $description = $schema['description'] ?? null;
        $prompt = $schema['prompt'] ?? null;
        $examples = $schema['examples'] ?? null;
        $maxRetries = $schema['maxRetries'] ?? $schema['max_retries'] ?? null;
        $llmOptions = $schema['llmOptions'] ?? $schema['llm_options'] ?? null;

        return new SchemaDefinition(
            class: $class,
            description: is_string($description) ? $description : null,
            prompt: is_string($prompt) ? $prompt : null,
            examples: is_array($examples) ? $examples : null,
            maxRetries: is_int($maxRetries) || is_numeric($maxRetries) ? (int) $maxRetries : null,
            llmOptions: is_array($llmOptions) ? $llmOptions : null,
        );
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
            CanSendHttpRequests::class,
            AgentDefinitionRegistry::class,
            CanManageAgentDefinitions::class,
            ToolRegistry::class,
            CanManageTools::class,
            AgentCapabilityRegistry::class,
            CanManageAgentCapabilities::class,
            SchemaRegistry::class,
            CanManageSchemas::class,
            InMemorySessionStore::class,
            DatabaseSessionStore::class,
            CanStoreSessions::class,
            SessionRepository::class,
            DefinitionLoopFactory::class,
            CanInstantiateAgentLoop::class,
            SessionRuntime::class,
            CanManageAgentSessions::class,
            BroadcastConfig::class,
            NullAgentEventTransport::class,
            LaravelAgentEventTransport::class,
            CanBroadcastAgentEvents::class,
            LaravelAgentBroadcasting::class,
            TraceRegistry::class,
            Telemetry::class,
            CanExportObservations::class,
            CanFlushTelemetry::class,
            CanShutdownTelemetry::class,
            CanProjectTelemetry::class,
            RuntimeEventBridge::class,
            TelemetryBridgeState::class,
            Inference::class,
            Embeddings::class,
            StructuredOutput::class,
            CanCreateInference::class,
            CanCreateEmbeddings::class,
            CanCreateStructuredOutput::class,
            StructuredOutputFake::class,
            AgentCtrlFake::class,
            NativeAgentTesting::class,
        ];
    }
}
