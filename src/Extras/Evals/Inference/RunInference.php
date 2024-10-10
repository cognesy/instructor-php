<?php

namespace Cognesy\Instructor\Extras\Evals\Inference;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Instructor\Extras\Evals\Data\EvalInput;
use Cognesy\Instructor\Extras\Evals\Data\EvalSchema;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;

class RunInference implements CanExecuteExperiment
{
    private InferenceAdapter $inferenceAdapter;

    private string|array $messages;
    private Mode $mode;
    private string $connection;
    private bool $isStreamed;
    private int $maxTokens;
    private EvalSchema $schema;

    private string $answer;
    private LLMResponse $response;

    public function __construct() {
        $this->inferenceAdapter = new InferenceAdapter();
    }

    public function withEvalInput(EvalInput $input) : self {
        $this->messages = $input->messages;
        $this->mode = $input->mode;
        $this->connection = $input->connection;
        $this->isStreamed = $input->isStreamed;
        $this->maxTokens = $input->maxTokens;
        $this->schema = $input->evalSchema();
        return $this;
    }

    public function execute() : void {
        $this->response = $this->makeLLMResponse();
        $this->answer = $this->response->content();
    }

    public function getAnswer() : string {
        return $this->answer;
    }

    public function getLLMResponse() : LLMResponse {
        return $this->response;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeLLMResponse() : LLMResponse {
        return $this->inferenceAdapter->callInferenceFor(
            messages: $this->messages,
            mode: $this->mode,
            connection: $this->connection,
            evalSchema: $this->schema,
            isStreamed: $this->isStreamed,
            maxTokens: $this->maxTokens,
        );
    }
}