<?php declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;

it('uses provided runtime and preserves it across request mutations', function () {
    $runtime = new class implements CanCreateEmbeddings {
        public function create(EmbeddingsRequest $request): PendingEmbeddings {
            throw new RuntimeException('test');
        }
    };

    $embeddings = new Embeddings($runtime);
    $derived = $embeddings->withInputs(['hello']);

    expect($embeddings->runtime())->toBe($runtime);
    expect($derived->runtime())->toBe($runtime);
    expect($derived)->not->toBe($embeddings);
});

it('withRuntime returns new facade with replaced runtime', function () {
    $firstRuntime = new class implements CanCreateEmbeddings {
        public function create(EmbeddingsRequest $request): PendingEmbeddings {
            throw new RuntimeException('test');
        }
    };

    $secondRuntime = new class implements CanCreateEmbeddings {
        public function create(EmbeddingsRequest $request): PendingEmbeddings {
            throw new RuntimeException('test');
        }
    };

    $embeddings = new Embeddings($firstRuntime);
    $updated = $embeddings->withRuntime($secondRuntime);

    expect($updated)->not->toBe($embeddings);
    expect($updated->runtime())->toBe($secondRuntime);
    expect($embeddings->runtime())->toBe($firstRuntime);
});

it('delegates create to runtime with built request', function () {
    $runtime = new class implements CanCreateEmbeddings {
        public ?EmbeddingsRequest $captured = null;

        public function create(EmbeddingsRequest $request): PendingEmbeddings {
            $this->captured = $request;
            throw new RuntimeException('stop');
        }
    };

    $embeddings = (new Embeddings($runtime))
        ->withInputs(['Hello'])
        ->withModel('test-model')
        ->withOptions(['foo' => 'bar']);

    expect(fn() => $embeddings->create())->toThrow(RuntimeException::class, 'stop');
    expect($runtime->captured)->toBeInstanceOf(EmbeddingsRequest::class);
    expect($runtime->captured?->inputs())->toBe(['Hello']);
    expect($runtime->captured?->model())->toBe('test-model');
    expect($runtime->captured?->options())->toBe(['foo' => 'bar']);
});

it('supports request hydration and static constructor sugar', function () {
    $runtime = new class implements CanCreateEmbeddings {
        public ?EmbeddingsRequest $captured = null;

        public function create(EmbeddingsRequest $request): PendingEmbeddings {
            $this->captured = $request;
            throw new RuntimeException('stop');
        }
    };

    $request = new EmbeddingsRequest(
        input: ['hydrated'],
        model: 'embed-small',
        options: ['a' => 1],
    );

    $facade = Embeddings::fromRuntime($runtime)->withRequest($request);

    expect(fn() => $facade->create())->toThrow(RuntimeException::class, 'stop');
    expect($runtime->captured?->inputs())->toBe(['hydrated']);
    expect($runtime->captured?->model())->toBe('embed-small');
    expect(Embeddings::using('openai'))->toBeInstanceOf(Embeddings::class);
    expect(Embeddings::fromDsn('driver=openai,model=text-embedding-3-small'))->toBeInstanceOf(Embeddings::class);
});
