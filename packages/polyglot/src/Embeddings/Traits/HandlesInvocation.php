<?php

namespace Cognesy\Polyglot\Embeddings\Traits;

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
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

        return new PendingEmbeddings(
            request: $request,
            driver: $this->embeddingsProvider->createDriver(),
            events: $this->events,
        );
    }
}