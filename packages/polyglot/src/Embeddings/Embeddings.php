<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;

/**
 * Embeddings is a facade responsible for generating embeddings for provided input data
 */
final class Embeddings implements CanCreateEmbeddings
{
    use Traits\HandlesShortcuts;

    private EmbeddingsRequest $request;
    private CanCreateEmbeddings $runtime;

    public function __construct(
        ?CanCreateEmbeddings $runtime = null,
    ) {
        $this->request = EmbeddingsRequest::empty();
        $this->runtime = $runtime ?? EmbeddingsRuntime::fromProvider(EmbeddingsProvider::new());
    }

    public static function fromConfig(EmbeddingsConfig $config): self {
        return new self(EmbeddingsRuntime::fromConfig($config));
    }

    public static function fromProvider(EmbeddingsProvider $provider): self {
        return new self(EmbeddingsRuntime::fromProvider($provider));
    }

    public static function fromRuntime(CanCreateEmbeddings $runtime): self {
        return new self($runtime);
    }

    public static function using(string $preset, ?string $basePath = null): self {
        return self::fromConfig(EmbeddingsConfig::fromPreset($preset, $basePath));
    }

    public function withRuntime(CanCreateEmbeddings $runtime): self {
        $copy = clone $this;
        $copy->runtime = $runtime;
        return $copy;
    }

    public function withRequest(EmbeddingsRequest $request): static {
        $copy = clone $this;
        $copy->request = $request;
        return $copy;
    }

    public function withInputs(string|array $input) : static {
        $copy = clone $this;
        $copy->request = $copy->request->withInputs($input);
        return $copy;
    }

    public function withModel(string $model) : static {
        $copy = clone $this;
        $copy->request = $copy->request->withModel($model);
        return $copy;
    }

    public function withOptions(array $options) : static {
        $copy = clone $this;
        $copy->request = $copy->request->withOptions($options);
        return $copy;
    }

    public function withRetryPolicy(EmbeddingsRetryPolicy $retryPolicy) : static {
        $copy = clone $this;
        $copy->request = $copy->request->withRetryPolicy($retryPolicy);
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
        return $this
            ->withInputs($input)
            ->withOptions($options)
            ->withModel($model);
    }

    #[\Override]
    public function create(?EmbeddingsRequest $request = null): PendingEmbeddings {
        return $this->runtime->create($request ?? $this->request);
    }
}
