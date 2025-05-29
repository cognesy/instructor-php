<?php

namespace Cognesy\Polyglot\Embeddings\Traits;

use Cognesy\Polyglot\Embeddings\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\EmbeddingsResponse;
use InvalidArgumentException;

trait HandlesInvocation
{
    public function withRequest(EmbeddingsRequest $request) : static {
        $this->request = $request;
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
        $this->request->withAnyInput($input ?: $this->request->inputs());
        $this->request->withOptions(array_merge($this->request->options(), $options));
        $this->request->withModel($model ?: $this->request->model());
        return $this;
    }

    /**
     * Generates embeddings for the provided input data.
     * @return EmbeddingsResponse
     */
    public function create() : EmbeddingsResponse {
        if (empty($this->request->inputs())) {
            throw new InvalidArgumentException("Input data is required");
        }

        if (count($this->request->inputs()) > $this->config()->maxInputs) {
            throw new InvalidArgumentException("Number of inputs exceeds the limit of {$this->config()->maxInputs}");
        }

        if (empty($this->request->model())) {
            $this->request->withModel($this->config()->model);
        }

        return $this->driver()->handle($this->request);
    }
}