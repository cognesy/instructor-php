<?php declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;
use Cognesy\Polyglot\Tests\Support\TestConfig;
use Cognesy\Polyglot\Tests\Support\FakeEmbeddingsDriver;
use Cognesy\Config\Dsn;
use Cognesy\Events\Dispatchers\EventDispatcher;

it('uses provided runtime and preserves it across request mutations', function () {
    $runtime = new class implements CanCreateEmbeddings {
        public int $calls = 0;

        public function create(EmbeddingsRequest $request): PendingEmbeddings {
            $this->calls++;
            throw new RuntimeException('test');
        }
    };

    $embeddings = new Embeddings($runtime);
    $derived = $embeddings->withInputs(['hello']);

    expect($derived)->not->toBe($embeddings);
    expect(fn() => $embeddings->create())->toThrow(RuntimeException::class, 'test');
    expect(fn() => $derived->create())->toThrow(RuntimeException::class, 'test');
    expect($runtime->calls)->toBe(2);
});

it('withRuntime returns new facade with replaced runtime', function () {
    $firstRuntime = new class implements CanCreateEmbeddings {
        public int $calls = 0;

        public function create(EmbeddingsRequest $request): PendingEmbeddings {
            $this->calls++;
            throw new RuntimeException('test');
        }
    };

    $secondRuntime = new class implements CanCreateEmbeddings {
        public int $calls = 0;

        public function create(EmbeddingsRequest $request): PendingEmbeddings {
            $this->calls++;
            throw new RuntimeException('test');
        }
    };

    $embeddings = new Embeddings($firstRuntime);
    $updated = $embeddings->withRuntime($secondRuntime);

    expect($updated)->not->toBe($embeddings);
    expect(fn() => $updated->create())->toThrow(RuntimeException::class, 'test');
    expect(fn() => $embeddings->create())->toThrow(RuntimeException::class, 'test');
    expect($secondRuntime->calls)->toBe(1);
    expect($firstRuntime->calls)->toBe(1);
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

it('supports request hydration and typed constructor sugar', function () {
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
    expect(Embeddings::fromConfig(TestConfig::embeddings('openai')))->toBeInstanceOf(Embeddings::class);

    $raw = Dsn::fromString('driver=openai,model=text-embedding-3-small')->toArray();
    expect(Embeddings::fromConfig(EmbeddingsConfig::fromArray($raw)))->toBeInstanceOf(Embeddings::class);
});

it('rejects execution when request has no inputs', function () {
    $runtime = new EmbeddingsRuntime(
        driver: new FakeEmbeddingsDriver(),
        events: new EventDispatcher(name: 'test.embeddings.runtime'),
    );

    expect(fn() => $runtime->create(EmbeddingsRequest::empty()))
        ->toThrow(InvalidArgumentException::class, 'Input data is required');
});
