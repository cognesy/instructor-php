<?php

namespace Cognesy\Evals\Evals\Inference;

use Cognesy\Evals\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Evals\Evals\Data\EvalInput;
use Cognesy\Instructor\Enums\Mode;
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
        array $schema,
        Mode $mode,
        string $connection,
        bool $isStreamed
    ) {
        $this->query = $query;
        $this->modes = new InferenceModes(schema: $schema);
        $this->mode = $mode;
        $this->connection = $connection;
        $this->isStreamed = $isStreamed;
    }

    public static function executeFor(EvalInput $input) : self {
        $instance = new RunInference(
            query: $input->messages,
            schema: $input->schema,
            mode: $input->mode,
            connection: $input->connection,
            isStreamed: $input->isStreamed
        );
        $instance->response = $instance->makeLLMResponse();
        $instance->answer = $instance->response->content();
        return $instance;
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