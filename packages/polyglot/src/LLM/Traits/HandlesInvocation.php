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
        $this->with(
            messages: $request->messages(),
            model: $request->model(),
            tools: $request->tools(),
            toolChoice: $request->toolChoice(),
            responseFormat: $request->responseFormat(),
            options: $request->options(),
            mode: $request->outputMode()
        );
        $this->cachedContext = $request->cachedContext() ?? $this->cachedContext;
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
        $this->messages = $messages;
        $this->model = $model;
        $this->tools = $tools;
        $this->toolChoice = $toolChoice;
        $this->responseFormat = $responseFormat;
        $this->options = array_merge($this->options, $options);
        $this->streaming = $options['stream'] ?? $this->streaming;
        $this->mode = $mode;
        return $this;
    }

    public function create(): InferenceResponse {
        $options = ($this->streaming === true)
            ? array_merge($this->options, ['stream' => true])
            : $this->options;
        $request = new InferenceRequest(
            messages: $this->messages,
            model: $this->model,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
            options: $options,
            mode: $this->mode ?? OutputMode::Unrestricted,
            cachedContext: $this->cachedContext ?? null
        );
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