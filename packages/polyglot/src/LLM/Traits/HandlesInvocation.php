<?php

namespace Cognesy\Polyglot\LLM\Traits;

use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\Events\InferenceRequested;
use Cognesy\Polyglot\LLM\InferenceRequest;
use Cognesy\Polyglot\LLM\InferenceResponse;

trait HandlesInvocation
{
    public function withRequest(InferenceRequest $request): static {
        $this->requestBuilder->withRequest($request);
        return $this;
    }

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