<?php

namespace Cognesy\Polyglot\Embeddings\Traits;

use Cognesy\Polyglot\Embeddings\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\EmbeddingsResponse;
use Cognesy\Utils\Json\Json;

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
     * @return EmbeddingsResponse
     */
    public function create() : EmbeddingsResponse {
        $request = new EmbeddingsRequest(
            input: $this->inputs,
            options: $this->options,
            model: $this->model
        );

        $response = $this->makeResponse($request);
        $this->events->dispatch(new EmbeddingsResponseReceived($response));

        return $response;
    }

    private function makeResponse(EmbeddingsRequest $request): EmbeddingsResponse {
        $driver = $this->embeddingsProvider->createDriver();
        $httpResponse = $driver->handle($request);
        $data = Json::decode($httpResponse->body()) ?? [];
        return $driver->fromData($data);
    }
}