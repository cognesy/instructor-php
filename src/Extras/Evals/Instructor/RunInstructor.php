<?php

namespace Cognesy\Instructor\Extras\Evals\Instructor;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Instructor\Extras\Evals\Data\EvalInput;
use Cognesy\Instructor\Features\Core\InstructorResponse;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Instructor;

class RunInstructor implements CanExecuteExperiment
{
    public string|array $messages = '';
    public string|array|object $responseModel = [];
    public string $connection = '';
    public Mode $mode = Mode::Json;
    public bool $withStreaming = false;
    public int $maxTokens = 4096;
    public string $toolName = '';
    public string $toolDescription = '';

    public string $system = '';
    public string $prompt = '';
    public string|array|object $input = '';
    public array $examples = [];
    public string $model = '';
    public string $retryPrompt = '';
    public int $maxRetries = 0;

    private InstructorResponse $instructorResponse;
    private LLMResponse $llmResponse;
    private mixed $answer;

    public function withEvalInput(EvalInput $input) : self {
        $this->messages = $input->messages;
        $this->responseModel = $input->responseSchema();
        $this->connection = $input->connection;
        $this->mode = $input->mode;
        $this->withStreaming = $input->isStreamed;
        //$this->toolName = $input->responseSchema()->toolName;
        //$this->toolDescription = $input->responseSchema()->toolDescription;
        return $this;
    }

    public function execute() : void {
        $this->instructorResponse = $this->makeInstructorResponse();
        $this->llmResponse = $this->instructorResponse->response();
        $this->answer = $this->llmResponse->value();
    }

    public function getAnswer() : mixed {
        return $this->answer;
    }

    public function getLLMResponse() : LLMResponse {
        return $this->llmResponse;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeInstructorResponse() : InstructorResponse {
        return (new Instructor)
            ->withConnection($this->connection)
            ->request(
                messages: $this->messages,
                input: $this->input,
                responseModel: $this->responseModel,
                system: $this->system,
                prompt: $this->prompt,
                examples: $this->examples,
                model: $this->model,
                maxRetries: $this->maxRetries,
                options: [
                    'max_tokens' => $this->maxTokens,
                    'stream' => $this->withStreaming,
                ],
                toolName: $this->toolName,
                toolDescription: $this->toolDescription,
                retryPrompt: $this->retryPrompt,
                mode: $this->mode,
            );
    }
}
