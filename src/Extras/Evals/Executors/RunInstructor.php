<?php

namespace Cognesy\Instructor\Extras\Evals\Executors;

use Cognesy\Instructor\Extras\Evals\Contracts\CanBeExecuted;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InstructorData;
use Cognesy\Instructor\Features\Core\InstructorResponse;
use Cognesy\Instructor\Instructor;

class RunInstructor implements CanBeExecuted
{
    private InstructorData $data;

    public function __construct(InstructorData $data) {
        $this->data = $data;
    }

    public function execute(Execution $execution) : Execution {
        $execution->response = $this->makeInstructorResponse($execution)->response();
        return $execution;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeInstructorResponse(Execution $execution) : InstructorResponse {
        return (new Instructor)
            ->withConnection($execution->connection)
            ->request(
                messages: $this->data->messages,
                input: $this->data->input,
                responseModel: $this->data->responseModel(),
                system: $this->data->system,
                prompt: $this->data->prompt,
                examples: $this->data->examples,
                model: $this->data->model,
                maxRetries: $this->data->maxRetries,
                options: [
                    'max_tokens' => $this->data->maxTokens,
                    'temperature' => $this->data->temperature,
                    'stream' => $execution->isStreamed,
                ],
                toolName: $this->data->toolName,
                toolDescription: $this->data->toolDescription,
                retryPrompt: $this->data->retryPrompt,
                mode: $execution->mode,
            );
    }
}
