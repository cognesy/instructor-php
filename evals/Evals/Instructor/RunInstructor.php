<?php

namespace Cognesy\Evals\Evals\Instructor;

use Cognesy\Evals\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Evals\Evals\Data\EvalInput;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\Core\InstructorResponse;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Instructor;

class RunInstructor implements CanExecuteExperiment
{
    public $maxTokens = 4096;
    public $toolName = '';
    public $toolDescription = '';
    public $retryPrompt = '';
    public int $maxRetries = 0;
    public string $system = '';

    private InstructorResponse $instructorResponse;
    private mixed $answer;
    private LLMResponse $llmResponse;

    public function __construct(
        readonly public string|array        $messages,
        readonly public string|array|object $responseModel,
        readonly public string              $connection,
        readonly public Mode                $mode = Mode::Json,
        readonly public bool                $withStreaming = false,
        readonly public string              $prompt = '',
        readonly public string|array|object $input = '',
        readonly public array               $examples = [],
        readonly public string              $model = '',
    ) {}

    public static function executeFor(EvalInput $input) : self {
        $instance = new RunInstructor(
            messages: $input->messages,
            responseModel: $input->schema,
            connection: $input->connection,
            mode: $input->mode,
            withStreaming: $input->isStreamed,
            prompt: '',
            input: '',
            examples: [],
            model: '',
        );
        $instance->instructorResponse = $instance->makeInstructorResponse();
        $instance->llmResponse = $instance->instructorResponse->response();
        $instance->answer = $instance->llmResponse->value();
        return $instance;
    }

    public function getAnswer() : mixed {
        return $this->answer;
    }

    public function getLLMResponse() : LLMResponse {
        return $this->llmResponse;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeInstructorResponse() : InstructorResponse {
        $response = (new Instructor)
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
        $this->instructorResponse = $response;
        return $response;
    }
}
