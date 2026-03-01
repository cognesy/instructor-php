<?php declare(strict_types=1);

use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Laravel\InstructorServiceProvider;
use Cognesy\Instructor\Laravel\Testing\AgentCtrlFake;
use Cognesy\Instructor\Laravel\Testing\StructuredOutputFake;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\PendingInference;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as LaravelDispatcher;
use Illuminate\Http\Client\Factory as LaravelHttpFactory;

final class TestConfigRepository implements ArrayAccess
{
    private array $items = [];

    public function __construct(array $items = []) {
        $this->items = $items;
    }

    public function get(string $key, mixed $default = null): mixed {
        $segments = explode('.', $key);
        $value = $this->items;
        foreach ($segments as $segment) {
            if (!is_array($value)) {
                return $default;
            }
            if (!array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public function set(string $key, mixed $value): void {
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

    public function has(string $key): bool {
        $marker = new stdClass();
        return $this->get($key, $marker) !== $marker;
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool {
        if (!is_string($offset)) {
            return false;
        }
        return $this->has($offset);
    }

    #[\Override]
    public function offsetGet(mixed $offset): mixed {
        if (!is_string($offset)) {
            return null;
        }
        return $this->get($offset);
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void {
        if (!is_string($offset)) {
            return;
        }
        $this->set($offset, $value);
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void {
        if (!is_string($offset)) {
            return;
        }
        $this->set($offset, null);
    }
}

final class TestLaravelDispatcher implements LaravelDispatcher
{
    /**
     * @param string|array<int, string> $events
     * @param (callable(mixed ...$payload): mixed)|string|null $listener
     */
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

    /**
     * @param string|array<int, string> $events
     * @param (callable(mixed ...$payload): mixed)|string|null $listener
     */
    public function listen($events, $listener = null): void {}
    public function hasListeners($eventName): bool { return false; }
    public function subscribe($subscriber): void {}
    public function until($event, $payload = []): mixed { return null; }
    public function dispatch($event, $payload = [], $halt = false): mixed {
        $this->dispatched[] = $event;
        return $event;
    }
    public function push($event, $payload = []): void {}
    public function flush($event): void {}
    public function forget($event): void {}
    public function forgetPushed(): void {}
}

final class AllowedBridgeEvent
{
    public function __construct(public string $value) {}
}

final class BlockedBridgeEvent
{
    public function __construct(public string $value) {}
}

function makeLaravelContainer(array $configOverrides = [], ?LaravelDispatcher $dispatcher = null): Container {
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

it('registers runtime creator contracts as singletons and supports create(request) paths', function () {
    $app = makeLaravelContainer();
    /** @phpstan-ignore-next-line */
    (new InstructorServiceProvider($app))->register();

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

it('keeps facade bindings and fakes working alongside runtime contract bindings', function () {
    $app = makeLaravelContainer();
    /** @phpstan-ignore-next-line */
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

    /** @phpstan-ignore-next-line */
    (new InstructorServiceProvider($app))->register();

    $httpClient = $app->make(\Cognesy\Http\HttpClient::class);

    $httpClientReflection = new ReflectionObject($httpClient);
    $driverProperty = $httpClientReflection->getProperty('driver');
    $driverProperty->setAccessible(true);
    $driver = $driverProperty->getValue($httpClient);

    $driverReflection = new ReflectionObject($driver);
    $factoryProperty = $driverReflection->getProperty('factory');
    $factoryProperty->setAccessible(true);
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

    /** @phpstan-ignore-next-line */
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

    /** @phpstan-ignore-next-line */
    (new InstructorServiceProvider($app))->register();

    $events = $app->make(CanHandleEvents::class);
    $events->dispatch(new AllowedBridgeEvent('allowed'));
    $events->dispatch(new BlockedBridgeEvent('blocked'));

    expect($dispatcher->dispatched)->toHaveCount(1)
        ->and($dispatcher->dispatched[0])->toBeInstanceOf(AllowedBridgeEvent::class);
});
