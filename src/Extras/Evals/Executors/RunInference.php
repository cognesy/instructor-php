<?php

namespace Cognesy\Instructor\Extras\Evals\Executors;

use Cognesy\Instructor\Extras\Evals\Contracts\CanBeExecuted;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InferenceData;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;

class RunInference implements CanBeExecuted
{
    private InferenceAdapter $inferenceAdapter;
    private InferenceData $data;

    public function __construct(InferenceData $data) {
        $this->inferenceAdapter = new InferenceAdapter();
        $this->data = $data;
    }

    public function execute(Execution $execution) : Execution {
        $execution->response = $this->makeLLMResponse($execution);
        return $execution;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeLLMResponse(Execution $execution) : LLMResponse {
        return $this->inferenceAdapter->callInferenceFor(
            connection: $execution->connection,
            mode: $execution->mode,
            isStreamed: $execution->isStreamed,
            messages: $this->data->messages,
            evalSchema: $this->data->inferenceSchema(),
            maxTokens: $this->data->maxTokens,
        );
    }
}