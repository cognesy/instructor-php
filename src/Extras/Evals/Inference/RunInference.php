<?php

namespace Cognesy\Instructor\Extras\Evals\Inference;

use Cognesy\Instructor\Extras\Evals\Data\Experiment;
use Cognesy\Instructor\Extras\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;

class RunInference implements CanExecuteExperiment
{
    private InferenceAdapter $inferenceAdapter;

    private string $answer;
    private LLMResponse $response;

    public function __construct() {
        $this->inferenceAdapter = new InferenceAdapter();
    }

    public function execute(Experiment $experiment) : void {
        $this->response = $this->makeLLMResponse($experiment);
        $this->answer = $this->response->content();
    }

    public function getAnswer() : string {
        return $this->answer;
    }

    public function getLLMResponse() : LLMResponse {
        return $this->response;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeLLMResponse(Experiment $experiment) : LLMResponse {
        return $this->inferenceAdapter->callInferenceFor(
            connection: $experiment->connection,
            mode: $experiment->mode,
            isStreamed: $experiment->isStreamed,
            messages: $experiment->data->messages,
            evalSchema: $experiment->data->inferenceSchema(),
            maxTokens: $experiment->data->maxTokens,
        );
    }
}