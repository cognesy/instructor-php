<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\EmbeddingsDriverFactory;

/**
 * Embeddings is a facade responsible for generating embeddings for provided input data
 */
final class Embeddings implements CanCreateEmbeddings
{
    use Traits\HandlesFluentMethods;
    use Traits\HandlesShortcuts;

    private CanCreateEmbeddings $runtime;

    public function __construct(
        ?CanCreateEmbeddings $runtime = null,
    ) {
        $this->runtime = $runtime ?? EmbeddingsRuntime::fromProvider(EmbeddingsProvider::new());
    }

    public static function using(string $preset): self {
        return new self(EmbeddingsRuntime::using($preset));
    }

    public static function fromDsn(string $dsn): self {
        return new self(EmbeddingsRuntime::fromDsn($dsn));
    }

    public static function fromRuntime(CanCreateEmbeddings $runtime): self {
        return new self($runtime);
    }

    public function withRuntime(CanCreateEmbeddings $runtime): self {
        $copy = clone $this;
        $copy->runtime = $runtime;
        return $copy;
    }

    public function runtime(): CanCreateEmbeddings {
        return $this->runtime;
    }

    public function withRequest(EmbeddingsRequest $request): static {
        $copy = clone $this;
        $copy->inputs = $request->inputs();
        $copy->options = $request->options();
        $copy->model = $request->model();
        $copy->retryPolicy = $request->retryPolicy();
        return $copy;
    }

    /**
     * @param string|array<int, string> $input
     * @param array<string, mixed> $options
     */
    public function with(
        string|array $input = [],
        array $options = [],
        string $model = '',
    ) : static {
        $copy = clone $this;
        $copy->inputs = $input;
        $copy->options = $options;
        $copy->model = $model;
        return $copy;
    }

    public function create(?EmbeddingsRequest $request = null): PendingEmbeddings {
        $request ??= new EmbeddingsRequest(
            input: $this->inputs,
            options: $this->options,
            model: $this->model,
            retryPolicy: $this->retryPolicy,
        );

        return $this->runtime->create($request);
    }

    /**
     * @param string|callable $driver
     */
    public static function registerDriver(string $name, string|callable $driver) : void {
        EmbeddingsDriverFactory::registerDriver($name, $driver);
    }
}
