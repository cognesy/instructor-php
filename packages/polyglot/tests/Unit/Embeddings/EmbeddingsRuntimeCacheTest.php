<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Embeddings;

it('reuses runtime when infrastructure remains unchanged', function () {
    $driver = new class implements CanHandleVectorization {
        public function handle(EmbeddingsRequest $request): HttpResponse {
            return HttpResponse::empty();
        }

        public function fromData(array $data): ?EmbeddingsResponse {
            return null;
        }
    };

    $embeddings = (new Embeddings())
        ->withConfig(new EmbeddingsConfig(model: 'test-embed', driver: 'openai'))
        ->withDriver($driver);

    $first = $embeddings->toRuntime();
    $second = $embeddings->toRuntime();

    expect($second)->toBe($first);
});

it('does not invalidate runtime cache on request-only mutations', function () {
    $driver = new class implements CanHandleVectorization {
        public function handle(EmbeddingsRequest $request): HttpResponse {
            return HttpResponse::empty();
        }

        public function fromData(array $data): ?EmbeddingsResponse {
            return null;
        }
    };

    $embeddings = (new Embeddings())
        ->withConfig(new EmbeddingsConfig(model: 'test-embed', driver: 'openai'))
        ->withDriver($driver);

    $first = $embeddings->toRuntime();
    $second = $embeddings->withInputs(['hello'])->toRuntime();

    expect($second)->toBe($first);
});

it('invalidates runtime cache on infrastructure mutations', function () {
    $driver = new class implements CanHandleVectorization {
        public function handle(EmbeddingsRequest $request): HttpResponse {
            return HttpResponse::empty();
        }

        public function fromData(array $data): ?EmbeddingsResponse {
            return null;
        }
    };

    $embeddings = (new Embeddings())
        ->withConfig(new EmbeddingsConfig(model: 'test-embed', driver: 'openai'))
        ->withDriver($driver);

    $first = $embeddings->toRuntime();
    $second = $embeddings->withHttpDebugPreset('on')->toRuntime();

    expect($second)->not->toBe($first);
});

it('invalidates runtime cache when event handler is replaced', function () {
    $driver = new class implements CanHandleVectorization {
        public function handle(EmbeddingsRequest $request): HttpResponse {
            return HttpResponse::empty();
        }

        public function fromData(array $data): ?EmbeddingsResponse {
            return null;
        }
    };

    $embeddings = (new Embeddings())
        ->withConfig(new EmbeddingsConfig(model: 'test-embed', driver: 'openai'))
        ->withDriver($driver);

    $first = $embeddings->toRuntime();
    $second = $embeddings->withEventHandler(new EventDispatcher())->toRuntime();

    expect($second)->not->toBe($first);
});

it('returns a new facade instance from with mutators', function () {
    $driver = new class implements CanHandleVectorization {
        public function handle(EmbeddingsRequest $request): HttpResponse {
            return HttpResponse::empty();
        }

        public function fromData(array $data): ?EmbeddingsResponse {
            return null;
        }
    };

    $embeddings = (new Embeddings())
        ->withConfig(new EmbeddingsConfig(model: 'test-embed', driver: 'openai'))
        ->withDriver($driver);

    $derived = $embeddings->withInputs(['hello']);

    expect($derived)->not->toBe($embeddings);
});
