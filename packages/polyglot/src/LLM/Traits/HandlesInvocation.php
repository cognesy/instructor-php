<?php

namespace Cognesy\Polyglot\LLM\Traits;

use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\Events\InferenceRequested;
use Cognesy\Polyglot\LLM\InferenceRequest;
use Cognesy\Polyglot\LLM\InferenceResponse;

trait HandlesInvocation
{
    /**
     * Sets the inference request object for the current instance.
     *
     * @param InferenceRequest $request The inference request object.
     */
    public function withRequest(InferenceRequest $request): static {
        $this->requestBuilder->withRequest($request);
        return $this;
    }

    /**
     * Sets the parameters for the inference request and returns the current instance.
     *
     * @param string|array $messages The input messages for the inference.
     * @param string $model The model to be used for the inference.
     * @param array $tools The tools to be used for the inference.
     * @param string|array $toolChoice The choice of tools for the inference.
     * @param array $responseFormat The format of the response.
     * @param array $options Additional options for the inference.
     * @param OutputMode $mode The mode of operation for the inference.
     */
    public function with(
        string|array $messages = [],
        string       $model = '',
        array        $tools = [],
        string|array $toolChoice = [],
        array        $responseFormat = [],
        array        $options = [],
        ?OutputMode  $mode = null,
    ) : static {
        $this->requestBuilder->withMessages($messages);
        $this->requestBuilder->withModel($model);
        $this->requestBuilder->withTools($tools);
        $this->requestBuilder->withToolChoice($toolChoice);
        $this->requestBuilder->withResponseFormat($responseFormat);
        $this->requestBuilder->withOptions($options);
        $this->requestBuilder->withOutputMode($mode);
        return $this;
    }

    public function create(): InferenceResponse {
        $request = $this->requestBuilder->create();
        $this->events->dispatch(new InferenceRequested($request));
        $inferenceDriver = $this->llmProvider->createDriver();
        return new InferenceResponse(
            httpResponse: $inferenceDriver->handle($request),
            driver: $inferenceDriver,
            isStreamed: $request->isStreamed(),
            events: $this->events,
        );
    }
}