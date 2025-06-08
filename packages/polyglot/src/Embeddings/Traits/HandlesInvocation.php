<?php

namespace Cognesy\Polyglot\Embeddings\Traits;

use Cognesy\Polyglot\Embeddings\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsRequested;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;

trait HandlesInvocation
{
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

        $this->events->dispatch(new EmbeddingsRequested([$request->toArray()]));
        $driver = $this->embeddingsProvider->createDriver();

        return new PendingEmbeddings(
            request: $this->request,
            driver: $driver,
            events: $this->events,
        );
    }
}