<?php

namespace Cognesy\Evals\Executors;

use Cognesy\Evals\Contracts\CanRunExecution;
use Cognesy\Evals\Execution;
use Cognesy\Evals\Executors\Data\StructuredOutputData;
use Cognesy\Instructor\Features\Core\StructuredOutputResponse;
use Cognesy\Instructor\StructuredOutput;

class RunInstructor implements CanRunExecution
{
    private StructuredOutputData $structuredOutputData;

    public function __construct(StructuredOutputData $data) {
        $this->structuredOutputData = $data;
    }

    public function run(Execution $execution) : Execution {
        $execution->data()->set('response', $this->makeInstructorResponse($execution)->response());
        return $execution;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeInstructorResponse(Execution $execution) : StructuredOutputResponse {
        return (new StructuredOutput)
            ->withConnection($execution->get('case.connection'))
            ->request(
                messages: $this->structuredOutputData->messages,
                input: $this->structuredOutputData->input,
                responseModel: $this->structuredOutputData->responseModel(),
                system: $this->structuredOutputData->system,
                prompt: $this->structuredOutputData->prompt,
                examples: $this->structuredOutputData->examples,
                model: $this->structuredOutputData->model,
                maxRetries: $this->structuredOutputData->maxRetries,
                options: [
                    'max_tokens' => $this->structuredOutputData->maxTokens,
                    'temperature' => $this->structuredOutputData->temperature,
                    'stream' => $execution->get('case.isStreamed'),
                ],
                toolName: $this->structuredOutputData->toolName,
                toolDescription: $this->structuredOutputData->toolDescription,
                retryPrompt: $this->structuredOutputData->retryPrompt,
                mode: $execution->get('case.mode'),
            );
    }
}
