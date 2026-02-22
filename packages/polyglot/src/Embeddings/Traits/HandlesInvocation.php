<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Traits;

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Embeddings\Contracts\HasExplicitEmbeddingsDriver;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\EmbeddingsDriverFactory;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;

// EmbeddingsRequested dispatched by driver; avoid duplicate here

trait HandlesInvocation
{
    /** @var EmbeddingsDriverFactory|null */
    private ?EmbeddingsDriverFactory $embeddingsFactory = null;
    private ?int $embeddingsFactoryEventBusId = null;

    private function getEmbeddingsFactory(): EmbeddingsDriverFactory {
        $eventsId = spl_object_id($this->events);
        if ($this->embeddingsFactory === null || $this->embeddingsFactoryEventBusId !== $eventsId) {
            $this->embeddingsFactory = new EmbeddingsDriverFactory($this->events);
            $this->embeddingsFactoryEventBusId = $eventsId;
        }
        return $this->embeddingsFactory;
    }

    public function withRequest(EmbeddingsRequest $request) : static {
        $copy = clone $this;
        $copy->inputs = $request->inputs();
        $copy->options = $request->options();
        $copy->model = $request->model();
        $copy->retryPolicy = $request->retryPolicy();
        return $copy;
    }

    /**
     * Sets provided input and options data.
     * @param string|array $input
     * @param array $options
     * @return static
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

    /**
     * Generates embeddings for the provided input data.
     * @return PendingEmbeddings
     */
    public function create(?EmbeddingsRequest $request = null) : PendingEmbeddings {
        $request = $request ?? new EmbeddingsRequest(
            input: $this->inputs,
            options: $this->options,
            model: $this->model,
            retryPolicy: $this->retryPolicy,
        );
        return $this->toRuntime()->create($request);
    }

    public function toRuntime(): EmbeddingsRuntime {
        if (!$this->runtimeCacheDirty && $this->runtimeCache !== null) {
            return $this->runtimeCache;
        }

        // EmbeddingsRequested will be emitted by the driver with normalized payload

        // Ensure HttpClient is available; build default if not provided
        if ($this->httpClient !== null) {
            $client = $this->httpClient;
        } else {
            $builder = new HttpClientBuilder(events: $this->events);
            if ($this->httpDebugPreset !== null) {
                $builder = $builder->withHttpDebugPreset($this->httpDebugPreset);
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

        $this->runtimeCache = new EmbeddingsRuntime(
            driver: $driver,
            events: $this->events,
        );
        $this->runtimeCacheDirty = false;
        return $this->runtimeCache;
    }
}
