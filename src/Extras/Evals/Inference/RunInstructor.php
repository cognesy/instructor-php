<?php

namespace Cognesy\Instructor\Extras\Evals\Inference;

use Cognesy\Instructor\Extras\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Instructor\Extras\Evals\Data\Experiment;
use Cognesy\Instructor\Features\Core\InstructorResponse;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Instructor;

class RunInstructor implements CanExecuteExperiment
{
    private InstructorResponse $instructorResponse;
    private LLMResponse $llmResponse;
    private mixed $answer;

    public function execute(Experiment $experiment) : void {
        $this->instructorResponse = $this->makeInstructorResponse($experiment);
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

    private function makeInstructorResponse(Experiment $experiment) : InstructorResponse {
        return (new Instructor)
            ->withConnection($experiment->connection)
            ->request(
                messages: $experiment->data->messages,
                input: $experiment->data->input,
                responseModel: $experiment->data->responseModel(),
                system: $experiment->data->system,
                prompt: $experiment->data->prompt,
                examples: $experiment->data->examples,
                model: $experiment->data->model,
                maxRetries: $experiment->data->maxRetries,
                options: [
                    'max_tokens' => $experiment->data->maxTokens,
                    'stream' => $experiment->isStreamed,
                ],
                toolName: $experiment->data->toolName,
                toolDescription: $experiment->data->toolDescription,
                retryPrompt: $experiment->data->retryPrompt,
                mode: $experiment->mode,
            );
    }
}
