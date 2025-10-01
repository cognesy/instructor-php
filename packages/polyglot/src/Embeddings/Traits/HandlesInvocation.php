<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Traits;

use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Embeddings\Contracts\HasExplicitEmbeddingsDriver;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\EmbeddingsDriverFactory;
// EmbeddingsRequested dispatched by driver; avoid duplicate here
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;

trait HandlesInvocation
{
    /** @var EmbeddingsDriverFactory|null */
    private ?EmbeddingsDriverFactory $embeddingsFactory = null;

    private function getEmbeddingsFactory(): EmbeddingsDriverFactory {
        return $this->embeddingsFactory ??= new EmbeddingsDriverFactory($this->events);
    }
    public function withRequest(EmbeddingsRequest $request) : static {
        $this->with(
            input: $request->inputs(),
            options: $request->options(),
            model: $request->model()
        );
        return $this;
    }

    /**
     * Sets provided input and options data.
     * @param string|array $input
     * @param array $options
     * @return self
     */
    public function with(
        string|array $input = [],
        array $options = [],
        string $model = '',
    ) : static {
        $this->inputs = $input;
        $this->options = $options;
        $this->model = $model;
        return $this;
    }

    /**
     * Generates embeddings for the provided input data.
     * @return PendingEmbeddings
     */
    public function create() : PendingEmbeddings {
        $request = new EmbeddingsRequest(
            input: $this->inputs,
            options: $this->options,
            model: $this->model
        );
        // EmbeddingsRequested will be emitted by the driver with normalized payload

        // Ensure HttpClient is available; build default if not provided
        if ($this->httpClient !== null) {
            $client = $this->httpClient;
        } else {
            $builder = new HttpClientBuilder(events: $this->events);
            if ($this->httpDebugPreset !== null) {
                $builder = $builder->withDebugPreset($this->httpDebugPreset);
            }
            $client = $builder->create();
        }

        // Prefer explicit driver if resolver/provider exposes it
        $resolver = $this->embeddingsResolver ?? $this->embeddingsProvider;
        if ($resolver instanceof HasExplicitEmbeddingsDriver) {
            $explicit = $resolver->explicitEmbeddingsDriver();
            if ($explicit !== null) {
                $driver = $explicit;
            } else {
                $config = $resolver->resolveConfig();
                $driver = $this->getEmbeddingsFactory()->makeDriver($config, $client);
            }
        } else {
            $config = $resolver->resolveConfig();
            $driver = $this->getEmbeddingsFactory()->makeDriver($config, $client);
        }

        return new PendingEmbeddings(
            request: $request,
            driver: $driver,
            events: $this->events,
        );
    }
}
