<?php

namespace Cognesy\Instructor\Extras\Evals\Inference;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Instructor\Extras\Evals\Data\EvalInput;
use Cognesy\Instructor\Extras\Evals\Data\EvalSchema;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;

class RunInference implements CanExecuteExperiment
{
    private InferenceModes $modes;
    private string|array $query;
    private LLMResponse $llmResponse;
    private Mode $mode;
    private string $connection;
    private bool $isStreamed;

    private string $answer;
    private LLMResponse $response;

    public function __construct(
        string|array $query,
        EvalSchema $schema,
        Mode $mode,
        string $connection,
        bool $isStreamed,
        int $maxTokens,
    ) {
        $this->query = $query;
        $this->modes = new InferenceModes(
            schema: $schema,
            maxTokens: $maxTokens
        );
        $this->mode = $mode;
        $this->connection = $connection;
        $this->isStreamed = $isStreamed;
    }

    public static function fromEvalInput(EvalInput $input) : self {
        $instance = new RunInference(
            query: $input->messages,
            schema: $input->evalSchema(),
            mode: $input->mode,
            connection: $input->connection,
            isStreamed: $input->isStreamed,
            maxTokens: $input->maxTokens,
        );
        return $instance;
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
        $this->llmResponse = $this->modes->callInferenceFor(
            $this->query,
            $this->mode,
            $this->connection,
            $this->modes->schema(),
            $this->isStreamed
        );
        return $this->llmResponse;
    }
}