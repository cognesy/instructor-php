<?php

namespace Cognesy\Evals\Executors;

use Cognesy\Evals\Contracts\CanRunExecution;
use Cognesy\Evals\Execution;
use Cognesy\Evals\Executors\Data\InstructorData;
use Cognesy\Instructor\Features\Core\StructuredOutputResponse;
use Cognesy\Instructor\Instructor;

class RunInstructor implements CanRunExecution
{
    private InstructorData $instructorData;

    public function __construct(InstructorData $data) {
        $this->instructorData = $data;
    }

    public function run(Execution $execution) : Execution {
        $execution->data()->set('response', $this->makeInstructorResponse($execution)->response());
        return $execution;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeInstructorResponse(Execution $execution) : StructuredOutputResponse {
        return (new Instructor)
            ->withConnection($execution->get('case.connection'))
            ->request(
                messages: $this->instructorData->messages,
                input: $this->instructorData->input,
                responseModel: $this->instructorData->responseModel(),
                system: $this->instructorData->system,
                prompt: $this->instructorData->prompt,
                examples: $this->instructorData->examples,
                model: $this->instructorData->model,
                maxRetries: $this->instructorData->maxRetries,
                options: [
                    'max_tokens' => $this->instructorData->maxTokens,
                    'temperature' => $this->instructorData->temperature,
                    'stream' => $execution->get('case.isStreamed'),
                ],
                toolName: $this->instructorData->toolName,
                toolDescription: $this->instructorData->toolDescription,
                retryPrompt: $this->instructorData->retryPrompt,
                mode: $execution->get('case.mode'),
            );
    }
}
