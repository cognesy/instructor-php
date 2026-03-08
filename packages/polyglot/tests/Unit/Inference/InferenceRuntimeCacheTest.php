<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Tests\Support\TestConfig;
use Cognesy\Config\Dsn;

it('uses provided runtime and preserves it across request mutations', function () {
    $runtime = new class implements CanCreateInference {
        public int $calls = 0;

        public function create(InferenceRequest $request): PendingInference {
            $this->calls++;
            throw new RuntimeException('test');
        }
    };

    $inference = new Inference($runtime);
    $derived = $inference->withMessages('hello');

    expect($derived)->not->toBe($inference);
    expect(fn() => $inference->create())->toThrow(RuntimeException::class, 'test');
    expect(fn() => $derived->create())->toThrow(RuntimeException::class, 'test');
    expect($runtime->calls)->toBe(2);
});

it('withRuntime returns new facade with replaced runtime', function () {
    $firstRuntime = new class implements CanCreateInference {
        public int $calls = 0;

        public function create(InferenceRequest $request): PendingInference {
            $this->calls++;
            throw new RuntimeException('test');
        }
    };

    $secondRuntime = new class implements CanCreateInference {
        public int $calls = 0;

        public function create(InferenceRequest $request): PendingInference {
            $this->calls++;
            throw new RuntimeException('test');
        }
    };

    $inference = new Inference($firstRuntime);
    $updated = $inference->withRuntime($secondRuntime);

    expect($updated)->not->toBe($inference);
    expect(fn() => $updated->create())->toThrow(RuntimeException::class, 'test');
    expect(fn() => $inference->create())->toThrow(RuntimeException::class, 'test');
    expect($secondRuntime->calls)->toBe(1);
    expect($firstRuntime->calls)->toBe(1);
});

it('delegates create to runtime with built request', function () {
    $runtime = new class implements CanCreateInference {
        public ?InferenceRequest $captured = null;

        public function create(InferenceRequest $request): PendingInference {
            $this->captured = $request;
            throw new RuntimeException('stop');
        }
    };

    $inference = (new Inference($runtime))
        ->withMessages('Hello')
        ->withModel('test-model');

    expect(fn() => $inference->create())->toThrow(RuntimeException::class, 'stop');
    expect($runtime->captured)->toBeInstanceOf(InferenceRequest::class);
    expect($runtime->captured?->model())->toBe('test-model');
    expect($runtime->captured?->messages()[0]['content'] ?? null)->toBe('Hello');
});

it('stream shortcut implies streaming intent', function () {
    $runtime = new class implements CanCreateInference {
        public ?InferenceRequest $captured = null;

        public function create(InferenceRequest $request): PendingInference {
            $this->captured = $request;

            return new PendingInference(
                execution: InferenceExecution::fromRequest($request),
                driver: new FakeInferenceDriver(
                    streamBatches: [[
                        new PartialInferenceResponse(contentDelta: 'Hello', finishReason: 'stop'),
                    ]],
                ),
                eventDispatcher: new EventDispatcher(),
            );
        }
    };

    $inference = (new Inference($runtime))->withMessages('Hello');
    $final = $inference->stream()->final();

    expect($runtime->captured)->toBeInstanceOf(InferenceRequest::class);
    expect($runtime->captured?->isStreamed())->toBeTrue();
    expect($final?->content())->toBe('Hello');
});

it('provides typed constructor sugar', function () {
    expect(Inference::fromConfig(TestConfig::llm('openai')))->toBeInstanceOf(Inference::class);

    $raw = Dsn::fromString('model=gpt-4o-mini')->toArray();
    expect(Inference::fromConfig(LLMConfig::fromArray($raw)))->toBeInstanceOf(Inference::class);
});
