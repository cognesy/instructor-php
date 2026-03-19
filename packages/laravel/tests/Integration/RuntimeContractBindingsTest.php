<?php declare(strict_types=1);

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\CanManageAgentCapabilities;
use Cognesy\Agents\Capability\StructuredOutput\CanManageSchemas;
use Cognesy\Agents\Capability\StructuredOutput\SchemaRegistry;
use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Broadcasting\BroadcastConfig;
use Cognesy\Agents\Broadcasting\CanBroadcastAgentEvents;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\ToolCallStarted;
use Cognesy\Agents\Session\Contracts\CanManageAgentSessions;
use Cognesy\Agents\Session\Contracts\CanStoreSessions;
use Cognesy\Agents\Session\Exceptions\SessionConflictException;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionFactory;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Tool\Contracts\CanDescribeTool;
use Cognesy\Agents\Tool\Contracts\CanManageTools;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Laravel\Agents\AgentRegistryTags;
use Cognesy\Instructor\Laravel\Agents\Broadcasting\LaravelAgentBroadcasting;
use Cognesy\Instructor\Laravel\Agents\Broadcasting\NullAgentEventTransport;
use Cognesy\Instructor\Laravel\Agents\Session\DatabaseSessionStore;
use Cognesy\Instructor\Laravel\Agents\SchemaRegistration;
use Cognesy\Instructor\Laravel\InstructorServiceProvider;
use Cognesy\Instructor\Laravel\Telemetry\NullTelemetryExporter;
use Cognesy\Instructor\Laravel\Testing\AgentCtrlFake;
use Cognesy\Instructor\Laravel\Testing\NativeAgentTesting;
use Cognesy\Instructor\Laravel\Testing\RecordingAgentEventTransport;
use Cognesy\Instructor\Laravel\Testing\RecordingTelemetryExporter as LaravelRecordingTelemetryExporter;
use Cognesy\Instructor\Laravel\Testing\StructuredOutputFake;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Messages\Messages;
use Cognesy\Telemetry\Application\Projector\CanProjectTelemetry;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Observation\Observation;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Utils\Result\Result;
use Illuminate\Contracts\Broadcasting\Broadcaster as LaravelBroadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade as SupportFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as LaravelDispatcher;
use Illuminate\Http\Client\Factory as LaravelHttpFactory;

final class TestConfigRepository implements ArrayAccess
{
    private array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $cursor =& $this->items;
        $last = array_pop($segments);

        if ($last === null) {
            return;
        }

        foreach ($segments as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor =& $cursor[$segment];
        }

        $cursor[$last] = $value;
    }

    public function has(string $key): bool
    {
        $marker = new stdClass();

        return $this->get($key, $marker) !== $marker;
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && $this->has($offset);
    }

    #[\Override]
    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) ? $this->get($offset) : null;
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_string($offset)) {
            $this->set($offset, $value);
        }
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        if (is_string($offset)) {
            $this->set($offset, null);
        }
    }
}

final class TestLaravelDispatcher implements LaravelDispatcher
{
    public function listen($events, $listener = null): void {}
    public function hasListeners($eventName): bool { return false; }
    public function subscribe($subscriber): void {}
    public function until($event, $payload = []): mixed { return null; }
    public function dispatch($event, $payload = [], $halt = false): mixed { return null; }
    public function push($event, $payload = []): void {}
    public function flush($event): void {}
    public function forget($event): void {}
    public function forgetPushed(): void {}
}

final class RecordingLaravelDispatcher implements LaravelDispatcher
{
    /** @var list<object|string> */
    public array $dispatched = [];

    public function listen($events, $listener = null): void {}
    public function hasListeners($eventName): bool { return false; }
    public function subscribe($subscriber): void {}
    public function until($event, $payload = []): mixed { return null; }
    public function dispatch($event, $payload = [], $halt = false): mixed
    {
        $this->dispatched[] = $event;
        return $event;
    }
    public function push($event, $payload = []): void {}
    public function flush($event): void {}
    public function forget($event): void {}
    public function forgetPushed(): void {}
}

final class RecordingLaravelBroadcaster implements LaravelBroadcaster
{
    /** @var list<array{channels: array, event: string, payload: array}> */
    public array $broadcasts = [];

    public function auth($request): mixed
    {
        return true;
    }

    public function validAuthenticationResponse($request, $result): mixed
    {
        return $result;
    }

    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $this->broadcasts[] = [
            'channels' => $channels,
            'event' => (string) $event,
            'payload' => $payload,
        ];
    }
}

final class RecordingBroadcastFactory implements BroadcastingFactory
{
    /** @var list<string|null> */
    public array $connections = [];

    public function __construct(
        private LaravelBroadcaster $broadcaster,
    ) {}

    public function connection($name = null): LaravelBroadcaster
    {
        $this->connections[] = is_string($name) ? $name : null;

        return $this->broadcaster;
    }
}

final class AllowedBridgeEvent
{
    public function __construct(public string $value) {}
}

final class BlockedBridgeEvent
{
    public function __construct(public string $value) {}
}

final class RegistryContributionDependency {}

final class DummyToolDescriptor implements CanDescribeTool
{
    public function __construct(private string $name) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return 'Dummy tool';
    }

    public function metadata(): array
    {
        return [];
    }

    public function instructions(): array
    {
        return [];
    }
}

final class ConfigRegistryTool implements ToolInterface
{
    public function __construct(private RegistryContributionDependency $dependency) {}

    public function use(mixed ...$args): Result
    {
        return Result::success('config-tool');
    }

    public function toToolSchema(): ToolDefinition
    {
        return new ToolDefinition('config-tool', 'Config tool', []);
    }

    public function descriptor(): CanDescribeTool
    {
        return new DummyToolDescriptor('config-tool');
    }
}

final class TaggedRegistryTool implements ToolInterface
{
    public function __construct(private RegistryContributionDependency $dependency) {}

    public function use(mixed ...$args): Result
    {
        return Result::success('tagged-tool');
    }

    public function toToolSchema(): ToolDefinition
    {
        return new ToolDefinition('tagged-tool', 'Tagged tool', []);
    }

    public function descriptor(): CanDescribeTool
    {
        return new DummyToolDescriptor('tagged-tool');
    }
}

final class ConfigRegistryCapability implements CanProvideAgentCapability
{
    public function __construct(private RegistryContributionDependency $dependency) {}

    public static function capabilityName(): string
    {
        return 'config-capability';
    }

    public function configure(CanConfigureAgent $agent): CanConfigureAgent
    {
        return $agent;
    }
}

final class TaggedRegistryCapability implements CanProvideAgentCapability
{
    public function __construct(private RegistryContributionDependency $dependency) {}

    public static function capabilityName(): string
    {
        return 'tagged-capability';
    }

    public function configure(CanConfigureAgent $agent): CanConfigureAgent
    {
        return $agent;
    }
}

final class ConfigSchemaData
{
    public function __construct(public readonly string $value) {}
}

final class TaggedSchemaData
{
    public function __construct(public readonly string $value) {}
}

final class RecordingTelemetryExporter implements CanExportObservations
{
    /** @var list<Observation> */
    public array $observations = [];

    public function exportObservation(Observation $observation): void
    {
        $this->observations[] = $observation;
    }
}

final class RecordingTelemetryProjector implements CanProjectTelemetry
{
    /** @var list<object> */
    public array $events = [];

    public function project(object $event): void
    {
        $this->events[] = $event;
    }
}

function makeLaravelContainer(array $configOverrides = [], ?LaravelDispatcher $dispatcher = null): Container
{
    $app = new Container();
    $config = array_replace_recursive([
        'instructor' => [
            'logging' => ['enabled' => false],
        ],
    ], $configOverrides);

    $app->instance('config', new TestConfigRepository($config));
    $app->instance(LaravelDispatcher::class, $dispatcher ?? new TestLaravelDispatcher());

    return $app;
}

function makeDatabaseResolver(): ConnectionResolverInterface
{
    $capsule = new Capsule();
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    return $capsule->getDatabaseManager();
}

function createAgentSessionsTable(ConnectionResolverInterface $database): void
{
    $app = new Container();
    $app->instance('db', $database);
    $app->instance('db.schema', $database->connection()->getSchemaBuilder());
    SupportFacade::setFacadeApplication($app);

    /** @var Migration $migration */
    $migration = require __DIR__ . '/../../database/migrations/2026_03_19_000000_create_instructor_agent_sessions_table.php';
    $migration->up();

    expect(Schema::hasTable('instructor_agent_sessions'))->toBeTrue();
}

function makeAgentSession(string $name = 'support-agent'): \Cognesy\Agents\Session\Data\AgentSession
{
    $definition = new AgentDefinition(
        name: $name,
        description: 'Support agent',
        systemPrompt: 'Help the user',
    );

    return (new SessionFactory(new DefinitionStateFactory()))->create($definition);
}

it('registers runtime creator contracts as singletons and supports create(request) paths', function () {
    $app = makeLaravelContainer();
    (new InstructorServiceProvider($app))->register();

    expect($app->bound(CanSendHttpRequests::class))->toBeTrue();
    expect($app->bound(CanCreateInference::class))->toBeTrue();
    expect($app->bound(CanCreateStructuredOutput::class))->toBeTrue();
    expect($app->bound(CanCreateEmbeddings::class))->toBeTrue();

    $inference = $app->make(CanCreateInference::class);
    $structuredOutput = $app->make(CanCreateStructuredOutput::class);
    $embeddings = $app->make(CanCreateEmbeddings::class);

    expect($app->make(CanCreateInference::class))->toBe($inference);
    expect($app->make(CanCreateStructuredOutput::class))->toBe($structuredOutput);
    expect($app->make(CanCreateEmbeddings::class))->toBe($embeddings);

    $pendingInference = $inference->create(new InferenceRequest(
        messages: Messages::fromString('Hello'),
    ));
    $pendingStructuredOutput = $structuredOutput->create(new StructuredOutputRequest(
        messages: Messages::fromString('Extract object'),
        requestedSchema: [
            'type' => 'object',
            'properties' => [
                'answer' => ['type' => 'string'],
            ],
            'required' => ['answer'],
        ],
    ));
    $pendingEmbeddings = $embeddings->create(new EmbeddingsRequest(input: 'hello'));

    expect($pendingInference)->toBeInstanceOf(PendingInference::class);
    expect($pendingStructuredOutput)->toBeInstanceOf(PendingStructuredOutput::class);
    expect($pendingEmbeddings)->toBeInstanceOf(PendingEmbeddings::class);
});

it('registers native agent runtime bindings as singletons and shares the Laravel event dispatcher', function () {
    $app = makeLaravelContainer();
    (new InstructorServiceProvider($app))->register();

    expect($app->bound(AgentDefinitionRegistry::class))->toBeTrue()
        ->and($app->bound(CanManageAgentDefinitions::class))->toBeTrue()
        ->and($app->bound(ToolRegistry::class))->toBeTrue()
        ->and($app->bound(CanManageTools::class))->toBeTrue()
        ->and($app->bound(AgentCapabilityRegistry::class))->toBeTrue()
        ->and($app->bound(CanManageAgentCapabilities::class))->toBeTrue()
        ->and($app->bound(SchemaRegistry::class))->toBeTrue()
        ->and($app->bound(CanManageSchemas::class))->toBeTrue()
        ->and($app->bound(InMemorySessionStore::class))->toBeTrue()
        ->and($app->bound(CanStoreSessions::class))->toBeTrue()
        ->and($app->bound(SessionRepository::class))->toBeTrue()
        ->and($app->bound(DefinitionLoopFactory::class))->toBeTrue()
        ->and($app->bound(CanInstantiateAgentLoop::class))->toBeTrue()
        ->and($app->bound(SessionRuntime::class))->toBeTrue()
        ->and($app->bound(CanManageAgentSessions::class))->toBeTrue();

    $definitions = $app->make(CanManageAgentDefinitions::class);
    $tools = $app->make(CanManageTools::class);
    $capabilities = $app->make(CanManageAgentCapabilities::class);
    $schemas = $app->make(CanManageSchemas::class);
    $store = $app->make(CanStoreSessions::class);
    $repository = $app->make(SessionRepository::class);
    $loopFactory = $app->make(CanInstantiateAgentLoop::class);
    $sessions = $app->make(CanManageAgentSessions::class);

    expect($definitions)->toBeInstanceOf(AgentDefinitionRegistry::class)
        ->and($definitions)->toBe($app->make(AgentDefinitionRegistry::class))
        ->and($tools)->toBeInstanceOf(ToolRegistry::class)
        ->and($tools)->toBe($app->make(ToolRegistry::class))
        ->and($capabilities)->toBeInstanceOf(AgentCapabilityRegistry::class)
        ->and($capabilities)->toBe($app->make(AgentCapabilityRegistry::class))
        ->and($schemas)->toBeInstanceOf(SchemaRegistry::class)
        ->and($schemas)->toBe($app->make(SchemaRegistry::class))
        ->and($store)->toBeInstanceOf(InMemorySessionStore::class)
        ->and($store)->toBe($app->make(InMemorySessionStore::class))
        ->and($loopFactory)->toBeInstanceOf(DefinitionLoopFactory::class)
        ->and($loopFactory)->toBe($app->make(DefinitionLoopFactory::class))
        ->and($sessions)->toBeInstanceOf(SessionRuntime::class)
        ->and($sessions)->toBe($app->make(SessionRuntime::class));

    $loopFactoryReflection = new ReflectionObject($loopFactory);
    $eventsProperty = $loopFactoryReflection->getProperty('events');
    $loopEvents = $eventsProperty->getValue($loopFactory);

    $sessionRuntimeReflection = new ReflectionObject($sessions);
    $repositoryProperty = $sessionRuntimeReflection->getProperty('sessions');
    $resolvedRepository = $repositoryProperty->getValue($sessions);
    $sessionEventsProperty = $sessionRuntimeReflection->getProperty('events');
    $sessionEvents = $sessionEventsProperty->getValue($sessions);

    $repositoryReflection = new ReflectionObject($repository);
    $storeProperty = $repositoryReflection->getProperty('store');
    $resolvedStore = $storeProperty->getValue($repository);

    expect($resolvedRepository)->toBe($repository)
        ->and($resolvedStore)->toBe($store)
        ->and($loopEvents)->toBe($app->make(CanHandleEvents::class))
        ->and($sessionEvents)->toBe($app->make(CanHandleEvents::class));
});

it('hydrates native agent registries from Laravel config and container tags', function () {
    $definitionPath = sys_get_temp_dir() . '/laravel-agent-definition-' . uniqid() . '.json';
    file_put_contents($definitionPath, json_encode([
        'name' => 'config-definition',
        'description' => 'Config definition',
        'systemPrompt' => 'Be helpful',
    ], JSON_THROW_ON_ERROR));

    try {
        $app = makeLaravelContainer(
            configOverrides: [
                'instructor' => [
                    'agents' => [
                        'definitions' => [$definitionPath],
                        'tools' => [ConfigRegistryTool::class],
                        'capabilities' => [ConfigRegistryCapability::class],
                        'schemas' => [
                            'config_schema' => ConfigSchemaData::class,
                        ],
                    ],
                ],
            ],
        );
        $app->instance(RegistryContributionDependency::class, new RegistryContributionDependency());

        $app->bind(TaggedRegistryTool::class, fn (Container $app) => new TaggedRegistryTool(
            $app->make(RegistryContributionDependency::class),
        ));
        $app->tag(TaggedRegistryTool::class, AgentRegistryTags::TOOLS);

        $app->bind(TaggedRegistryCapability::class, fn (Container $app) => new TaggedRegistryCapability(
            $app->make(RegistryContributionDependency::class),
        ));
        $app->tag(TaggedRegistryCapability::class, AgentRegistryTags::CAPABILITIES);

        $app->bind('tagged-agent-definition', fn () => new AgentDefinition(
            name: 'tagged-definition',
            description: 'Tagged definition',
            systemPrompt: 'Tag prompt',
        ));
        $app->tag('tagged-agent-definition', AgentRegistryTags::DEFINITIONS);

        $app->bind('tagged-schema-registration', fn () => new SchemaRegistration(
            name: 'tagged_schema',
            schema: TaggedSchemaData::class,
        ));
        $app->tag('tagged-schema-registration', AgentRegistryTags::SCHEMAS);

        (new InstructorServiceProvider($app))->register();

        $definitions = $app->make(CanManageAgentDefinitions::class);
        $tools = $app->make(CanManageTools::class);
        $capabilities = $app->make(CanManageAgentCapabilities::class);
        $schemas = $app->make(CanManageSchemas::class);

        expect($definitions->has('config-definition'))->toBeTrue()
            ->and($definitions->has('tagged-definition'))->toBeTrue()
            ->and($definitions->get('tagged-definition')->description)->toBe('Tagged definition')
            ->and($tools->has('config-tool'))->toBeTrue()
            ->and($tools->has('tagged-tool'))->toBeTrue()
            ->and($tools->get('config-tool'))->toBeInstanceOf(ConfigRegistryTool::class)
            ->and($tools->get('tagged-tool'))->toBeInstanceOf(TaggedRegistryTool::class)
            ->and($capabilities->has('config-capability'))->toBeTrue()
            ->and($capabilities->has('tagged-capability'))->toBeTrue()
            ->and($capabilities->get('config-capability'))->toBeInstanceOf(ConfigRegistryCapability::class)
            ->and($capabilities->get('tagged-capability'))->toBeInstanceOf(TaggedRegistryCapability::class)
            ->and($schemas->has('config_schema'))->toBeTrue()
            ->and($schemas->has('tagged_schema'))->toBeTrue()
            ->and($schemas->get('config_schema')->class)->toBe(ConfigSchemaData::class)
            ->and($schemas->get('tagged_schema')->class)->toBe(TaggedSchemaData::class);
    } finally {
        @unlink($definitionPath);
    }
});

it('uses database-backed native session storage when configured', function () {
    $database = makeDatabaseResolver();
    createAgentSessionsTable($database);

    $app = makeLaravelContainer(
        configOverrides: [
            'instructor' => [
                'agents' => [
                    'session_store' => 'database',
                ],
            ],
        ],
    );
    $app->instance(ConnectionResolverInterface::class, $database);

    (new InstructorServiceProvider($app))->register();

    $store = $app->make(CanStoreSessions::class);
    expect($store)->toBeInstanceOf(DatabaseSessionStore::class);

    $initial = makeAgentSession();
    $created = $store->create($initial);
    $loaded = $store->load($created->sessionId());
    $saved = $store->save($created->withState($created->state()->withUserMessage('Hello')));

    expect($created->version())->toBe(1)
        ->and($loaded?->sessionId()->equals($created->sessionId()))->toBeTrue()
        ->and($saved->version())->toBe(2)
        ->and($store->listHeaders()->count())->toBe(1)
        ->and($store->exists($created->sessionId()))->toBeTrue();

    $store->delete($created->sessionId());

    expect($store->exists($created->sessionId()))->toBeFalse()
        ->and($store->load($created->sessionId()))->toBeNull();
});

it('raises session conflicts for stale database-backed session saves', function () {
    $database = makeDatabaseResolver();
    createAgentSessionsTable($database);

    $app = makeLaravelContainer(
        configOverrides: [
            'instructor' => [
                'agents' => [
                    'session_store' => 'database',
                ],
            ],
        ],
    );
    $app->instance(ConnectionResolverInterface::class, $database);

    (new InstructorServiceProvider($app))->register();

    $store = $app->make(CanStoreSessions::class);
    $created = $store->create(makeAgentSession('conflict-agent'));
    $next = $created->withState($created->state()->withUserMessage('First'));

    $store->save($next);

    expect(fn () => $store->save($next))->toThrow(SessionConflictException::class);
});

it('builds native agent broadcasters from Laravel config and delivers envelopes through Laravel broadcasting', function () {
    $laravelBroadcaster = new RecordingLaravelBroadcaster();
    $broadcastFactory = new RecordingBroadcastFactory($laravelBroadcaster);
    $app = makeLaravelContainer(
        configOverrides: [
            'instructor' => [
                'agents' => [
                    'broadcasting' => [
                        'enabled' => true,
                        'connection' => 'reverb',
                        'event_name' => 'instructor.agent.event',
                        'preset' => 'debug',
                    ],
                ],
            ],
        ],
    );
    $app->instance(BroadcastingFactory::class, $broadcastFactory);

    (new InstructorServiceProvider($app))->register();

    $broadcasting = $app->make(LaravelAgentBroadcasting::class);
    $transport = $app->make(CanBroadcastAgentEvents::class);
    $config = $app->make(BroadcastConfig::class);
    $broadcaster = $broadcasting->broadcaster('session-1', 'exec-1');

    $broadcaster->onAgentStepStarted(new AgentStepStarted(
        agentId: 'agent-1',
        executionId: 'exec-1',
        parentAgentId: null,
        stepNumber: 1,
        messageCount: 2,
        availableTools: 1,
    ));
    $broadcaster->onToolCallStarted(new ToolCallStarted(
        agentId: 'agent-1',
        executionId: 'exec-1',
        parentAgentId: null,
        stepNumber: 1,
        tool: 'lookup-account',
        args: ['email' => 'person@example.com'],
        startedAt: new \DateTimeImmutable(),
    ));

    expect($transport)->not->toBeInstanceOf(NullAgentEventTransport::class)
        ->and($config->includeToolArgs)->toBeTrue()
        ->and($broadcastFactory->connections)->toBe(['reverb', 'reverb', 'reverb'])
        ->and($laravelBroadcaster->broadcasts)->toHaveCount(3)
        ->and($laravelBroadcaster->broadcasts[0]['channels'])->toBe(['agent.session-1'])
        ->and($laravelBroadcaster->broadcasts[0]['event'])->toBe('instructor.agent.event')
        ->and($laravelBroadcaster->broadcasts[0]['payload']['type'] ?? null)->toBe('agent.status')
        ->and($laravelBroadcaster->broadcasts[1]['payload']['type'] ?? null)->toBe('agent.step.started')
        ->and($laravelBroadcaster->broadcasts[2]['payload']['type'] ?? null)->toBe('agent.tool.started')
        ->and($laravelBroadcaster->broadcasts[2]['payload']['payload']['args'] ?? null)->toBe(['email' => 'person@example.com']);
});

it('provides Laravel-native testing helpers for native agent runtime surfaces', function () {
    $app = makeLaravelContainer(
        configOverrides: [
            'instructor' => [
                'telemetry' => [
                    'enabled' => true,
                ],
                'agents' => [
                    'broadcasting' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ],
    );

    (new InstructorServiceProvider($app))->register();

    $testing = $app->make(NativeAgentTesting::class);
    $driver = $testing->fakeDriver(FakeAgentDriver::fromResponses('done'));
    $sessions = $testing->fakeSessions();
    $broadcasts = $testing->fakeBroadcasts();
    $telemetry = $testing->captureTelemetry();

    expect($app->make(NativeAgentTesting::class))->toBe($testing)
        ->and($driver)->toBeInstanceOf(FakeAgentDriver::class)
        ->and($app->make(CanManageAgentCapabilities::class)->has('use_test_driver'))->toBeTrue()
        ->and($app->make(CanStoreSessions::class))->toBe($sessions)
        ->and($app->make(CanBroadcastAgentEvents::class))->toBe($broadcasts)
        ->and($broadcasts)->toBeInstanceOf(RecordingAgentEventTransport::class)
        ->and($app->make(CanExportObservations::class))->toBe($telemetry)
        ->and($telemetry)->toBeInstanceOf(LaravelRecordingTelemetryExporter::class);
});

it('wires telemetry into the shared event bus and exports native agent observations', function () {
    $app = makeLaravelContainer(
        configOverrides: [
            'instructor' => [
                'telemetry' => [
                    'enabled' => true,
                    'projectors' => [
                        'instructor' => false,
                        'polyglot' => false,
                        'http' => false,
                        'agent_ctrl' => false,
                        'agents' => true,
                    ],
                ],
            ],
        ],
    );
    $exporter = new RecordingTelemetryExporter();
    $app->instance(CanExportObservations::class, $exporter);

    (new InstructorServiceProvider($app))->register();

    $telemetry = $app->make(Telemetry::class);
    $bridge = $app->make(RuntimeEventBridge::class);
    $events = $app->make(CanHandleEvents::class);

    $events->dispatch(new AgentExecutionStarted(
        agentId: 'agent-1',
        executionId: 'exec-1',
        parentAgentId: null,
        messageCount: 2,
        availableTools: 1,
    ));
    $events->dispatch(new AgentExecutionCompleted(
        agentId: 'agent-1',
        executionId: 'exec-1',
        parentAgentId: null,
        status: ExecutionStatus::Completed,
        totalSteps: 1,
        totalUsage: new InferenceUsage(inputTokens: 3, outputTokens: 5),
        errors: null,
    ));

    expect($telemetry)->toBeInstanceOf(Telemetry::class)
        ->and($bridge)->toBeInstanceOf(RuntimeEventBridge::class)
        ->and($exporter->observations)->toHaveCount(1)
        ->and($exporter->observations[0]->name())->toBe('agent.execute')
        ->and($exporter->observations[0]->attributes()->toArray()['agent.total_steps'] ?? null)->toBe(1)
        ->and($exporter->observations[0]->attributes()->toArray()['inference.tokens.total'] ?? null)->toBe(8);
});

it('respects Laravel telemetry projector selection', function () {
    $app = makeLaravelContainer(
        configOverrides: [
            'instructor' => [
                'telemetry' => [
                    'enabled' => true,
                    'projectors' => [
                        'instructor' => false,
                        'polyglot' => false,
                        'http' => false,
                        'agent_ctrl' => false,
                        'agents' => false,
                    ],
                ],
            ],
        ],
    );
    $exporter = new RecordingTelemetryExporter();
    $app->instance(CanExportObservations::class, $exporter);

    (new InstructorServiceProvider($app))->register();

    $events = $app->make(CanHandleEvents::class);

    $events->dispatch(new AgentExecutionStarted(
        agentId: 'agent-2',
        executionId: 'exec-2',
        parentAgentId: null,
        messageCount: 1,
        availableTools: 0,
    ));
    $events->dispatch(new AgentExecutionCompleted(
        agentId: 'agent-2',
        executionId: 'exec-2',
        parentAgentId: null,
        status: ExecutionStatus::Completed,
        totalSteps: 1,
        totalUsage: InferenceUsage::none(),
        errors: null,
    ));

    expect($exporter->observations)->toBe([]);
});

it('defaults telemetry export to a null exporter when no backend is configured', function () {
    $app = makeLaravelContainer(
        configOverrides: [
            'instructor' => [
                'telemetry' => [
                    'enabled' => true,
                ],
            ],
        ],
    );

    (new InstructorServiceProvider($app))->register();

    expect($app->make(CanExportObservations::class))->toBeInstanceOf(NullTelemetryExporter::class);
});

it('keeps container-provided telemetry projector bindings intact', function () {
    $app = makeLaravelContainer(
        configOverrides: [
            'instructor' => [
                'telemetry' => [
                    'enabled' => true,
                ],
            ],
        ],
    );
    $projector = new RecordingTelemetryProjector();
    $app->instance(CanProjectTelemetry::class, $projector);

    (new InstructorServiceProvider($app))->register();

    $events = $app->make(CanHandleEvents::class);
    $events->dispatch(new AllowedBridgeEvent('telemetry'));

    expect($app->make(CanProjectTelemetry::class))->toBe($projector)
        ->and($projector->events)->toHaveCount(1)
        ->and($projector->events[0])->toBeInstanceOf(AllowedBridgeEvent::class);
});

it('keeps facade bindings and fakes working alongside runtime contract bindings', function () {
    $app = makeLaravelContainer();
    (new InstructorServiceProvider($app))->register();

    $inferenceFacade = $app->make(Inference::class);
    $embeddingsFacade = $app->make(Embeddings::class);
    $structuredFacade = $app->make(StructuredOutput::class);

    expect($inferenceFacade)->toBeInstanceOf(Inference::class);
    expect($embeddingsFacade)->toBeInstanceOf(Embeddings::class);
    expect($structuredFacade)->toBeInstanceOf(StructuredOutput::class);

    expect($app->make(StructuredOutputFake::class))->toBeInstanceOf(StructuredOutputFake::class);
    expect($app->make(AgentCtrlFake::class))->toBeInstanceOf(AgentCtrlFake::class);

    expect($app->make(CanCreateInference::class))->not->toBe($inferenceFacade);
    expect($app->make(CanCreateEmbeddings::class))->not->toBe($embeddingsFacade);
});

it('uses container-bound Laravel HTTP factory for laravel driver wiring', function () {
    $app = makeLaravelContainer();
    $factory = new LaravelHttpFactory();
    $app->instance(LaravelHttpFactory::class, $factory);

    (new InstructorServiceProvider($app))->register();

    $transport = $app->make(CanSendHttpRequests::class);
    $httpClient = $app->make(HttpClient::class);

    expect($transport)->toBe($httpClient);

    $driver = $httpClient->runtime()->driver();
    $driverReflection = new ReflectionObject($driver);
    $factoryProperty = $driverReflection->getProperty('factory');
    $resolvedFactory = $factoryProperty->getValue($driver);

    expect($resolvedFactory)->toBe($factory);
});

it('does not dispatch events to Laravel when bridge dispatch is disabled', function () {
    $dispatcher = new RecordingLaravelDispatcher();
    $app = makeLaravelContainer(
        configOverrides: [
            'instructor' => [
                'events' => [
                    'dispatch_to_laravel' => false,
                ],
            ],
        ],
        dispatcher: $dispatcher,
    );

    (new InstructorServiceProvider($app))->register();

    $events = $app->make(CanHandleEvents::class);
    $events->dispatch(new AllowedBridgeEvent('x'));

    expect($dispatcher->dispatched)->toBe([]);
});

it('dispatches only configured bridged event classes to Laravel', function () {
    $dispatcher = new RecordingLaravelDispatcher();
    $app = makeLaravelContainer(
        configOverrides: [
            'instructor' => [
                'events' => [
                    'dispatch_to_laravel' => true,
                    'bridge_events' => [AllowedBridgeEvent::class],
                ],
            ],
        ],
        dispatcher: $dispatcher,
    );

    (new InstructorServiceProvider($app))->register();

    $events = $app->make(CanHandleEvents::class);
    $events->dispatch(new AllowedBridgeEvent('allowed'));
    $events->dispatch(new BlockedBridgeEvent('blocked'));

    expect($dispatcher->dispatched)->toHaveCount(1)
        ->and($dispatcher->dispatched[0])->toBeInstanceOf(AllowedBridgeEvent::class);
});
