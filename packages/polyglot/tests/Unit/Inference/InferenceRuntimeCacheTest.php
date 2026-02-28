<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\PendingInference;

it('uses provided runtime and preserves it across request mutations', function () {
    $runtime = new class implements CanCreateInference {
        public function create(InferenceRequest $request): PendingInference {
            throw new RuntimeException('test');
        }
    };

    $inference = new Inference($runtime);
    $derived = $inference->withMessages('hello');

    expect($inference->runtime())->toBe($runtime);
    expect($derived->runtime())->toBe($runtime);
    expect($derived)->not->toBe($inference);
});

it('withRuntime returns new facade with replaced runtime', function () {
    $firstRuntime = new class implements CanCreateInference {
        public function create(InferenceRequest $request): PendingInference {
            throw new RuntimeException('test');
        }
    };

    $secondRuntime = new class implements CanCreateInference {
        public function create(InferenceRequest $request): PendingInference {
            throw new RuntimeException('test');
        }
    };

    $inference = new Inference($firstRuntime);
    $updated = $inference->withRuntime($secondRuntime);

    expect($updated)->not->toBe($inference);
    expect($updated->runtime())->toBe($secondRuntime);
    expect($inference->runtime())->toBe($firstRuntime);
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

it('provides static using and fromDsn constructor sugar', function () {
    expect(Inference::using('openai'))->toBeInstanceOf(Inference::class);
    expect(Inference::fromDsn('preset=openai,model=gpt-4o-mini'))->toBeInstanceOf(Inference::class);
});
