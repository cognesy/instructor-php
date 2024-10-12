<?php

namespace Cognesy\Instructor\Extras\Evals\Inference;

use Cognesy\Instructor\Extras\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Instructor\Extras\Evals\Data\InstructorData;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Features\Core\InstructorResponse;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Instructor;

class RunInstructor implements CanExecuteExperiment
{
    private InstructorData $data;

    public function __construct(InstructorData $data) {
        $this->data = $data;
    }

    public function execute(Experiment $experiment) : LLMResponse {
        return $this->makeInstructorResponse($experiment)->response();
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeInstructorResponse(Experiment $experiment) : InstructorResponse {
        return (new Instructor)
            ->withConnection($experiment->connection)
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
                    'stream' => $experiment->isStreamed,
                ],
                toolName: $this->data->toolName,
                toolDescription: $this->data->toolDescription,
                retryPrompt: $this->data->retryPrompt,
                mode: $experiment->mode,
            );
    }
}
