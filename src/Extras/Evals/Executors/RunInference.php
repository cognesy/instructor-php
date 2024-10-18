<?php

namespace Cognesy\Instructor\Extras\Evals\Executors;

use Cognesy\Instructor\Extras\Evals\Contracts\CanRunExecution;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InferenceData;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;

class RunInference implements CanRunExecution
{
    private InferenceAdapter $inferenceAdapter;
    private InferenceData $inferenceData;

    public function __construct(InferenceData $data) {
        $this->inferenceAdapter = new InferenceAdapter();
        $this->inferenceData = $data;
    }

    public function execute(Execution $execution) : Execution {
        $execution->data()->set('response', $this->makeLLMResponse($execution));
        return $execution;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeLLMResponse(Execution $execution) : LLMResponse {
        return $this->inferenceAdapter->callInferenceFor(
            connection: $execution->data()->get('connection'),
            mode: $execution->data()->get('mode'),
            isStreamed: $execution->data()->get('isStreamed'),
            messages: $this->inferenceData->messages,
            evalSchema: $this->inferenceData->inferenceSchema(),
            maxTokens: $this->inferenceData->maxTokens,
        );
    }
}