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
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\PendingInference;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as LaravelDispatcher;

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

function makeLaravelContainer(): Container {
    $app = new Container();
    $app->instance('config', new TestConfigRepository([
        'instructor' => [
            'logging' => ['enabled' => false],
        ],
    ]));
    $app->instance(LaravelDispatcher::class, new TestLaravelDispatcher());
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
