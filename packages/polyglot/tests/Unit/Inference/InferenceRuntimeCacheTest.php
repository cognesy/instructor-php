<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Inference;

it('reuses runtime when infrastructure remains unchanged', function () {
    $driver = new class implements CanProcessInferenceRequest {
        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            return new InferenceResponse(content: 'ok');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            return [];
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $inference = (new Inference())
        ->withLLMConfig(new LLMConfig(model: 'test-model'))
        ->withDriver($driver);

    $first = $inference->toRuntime();
    $second = $inference->toRuntime();

    expect($second)->toBe($first);
});

it('does not invalidate runtime cache on request-only mutations', function () {
    $driver = new class implements CanProcessInferenceRequest {
        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            return new InferenceResponse(content: 'ok');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            return [];
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $inference = (new Inference())
        ->withLLMConfig(new LLMConfig(model: 'test-model'))
        ->withDriver($driver);

    $first = $inference->toRuntime();
    $second = $inference->withMessages('hello')->toRuntime();

    expect($second)->toBe($first);
});

it('invalidates runtime cache on infrastructure mutations', function () {
    $driver = new class implements CanProcessInferenceRequest {
        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            return new InferenceResponse(content: 'ok');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            return [];
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $inference = (new Inference())
        ->withLLMConfig(new LLMConfig(model: 'test-model'))
        ->withDriver($driver);

    $first = $inference->toRuntime();
    $second = $inference->withHttpDebugPreset('on')->toRuntime();

    expect($second)->not->toBe($first);
});

it('invalidates runtime cache when event handler is replaced', function () {
    $driver = new class implements CanProcessInferenceRequest {
        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            return new InferenceResponse(content: 'ok');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            return [];
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $inference = (new Inference())
        ->withLLMConfig(new LLMConfig(model: 'test-model'))
        ->withDriver($driver);

    $first = $inference->toRuntime();
    $second = $inference->withEventHandler(new EventDispatcher())->toRuntime();

    expect($second)->not->toBe($first);
});

it('returns a new facade instance from with mutators', function () {
    $driver = new class implements CanProcessInferenceRequest {
        public function makeResponseFor(InferenceRequest $request): InferenceResponse {
            return new InferenceResponse(content: 'ok');
        }

        public function makeStreamResponsesFor(InferenceRequest $request): iterable {
            return [];
        }

        public function capabilities(?string $model = null): DriverCapabilities {
            return new DriverCapabilities();
        }
    };

    $inference = (new Inference())
        ->withLLMConfig(new LLMConfig(model: 'test-model'))
        ->withDriver($driver);

    $derived = $inference->withMessages('hello');

    expect($derived)->not->toBe($inference);
});
