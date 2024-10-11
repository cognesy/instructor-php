<?php

namespace Cognesy\Instructor\Extras\Evals\Inference;

use Cognesy\Instructor\Extras\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Instructor\Extras\Evals\Data\InferenceData;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;

class RunInference implements CanExecuteExperiment
{
    private InferenceAdapter $inferenceAdapter;
    private InferenceData $data;

    public function __construct(InferenceData $data) {
        $this->inferenceAdapter = new InferenceAdapter();
        $this->data = $data;
    }

    public function execute(Experiment $experiment) : LLMResponse {
        return $this->makeLLMResponse($experiment);
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeLLMResponse(Experiment $experiment) : LLMResponse {
        return $this->inferenceAdapter->callInferenceFor(
            connection: $experiment->connection,
            mode: $experiment->mode,
            isStreamed: $experiment->isStreamed,
            messages: $this->data->messages,
            evalSchema: $this->data->inferenceSchema(),
            maxTokens: $this->data->maxTokens,
        );
    }
}