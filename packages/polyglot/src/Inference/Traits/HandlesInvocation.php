<?php

namespace Cognesy\Polyglot\Inference\Traits;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\PendingInference;

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

    public function create(): PendingInference {
        $request = $this->requestBuilder->create();
        $inferenceDriver = $this->llmProvider->createDriver();
        return new PendingInference(
            request: $request,
            driver: $inferenceDriver,
            eventDispatcher: $this->events,
        );
    }
}