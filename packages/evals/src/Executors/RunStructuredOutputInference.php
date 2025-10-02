<?php declare(strict_types=1);

namespace Cognesy\Evals\Executors;

use Cognesy\Evals\Contracts\CanRunExecution;
use Cognesy\Evals\Execution;
use Cognesy\Evals\Executors\Data\StructuredOutputData;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Instructor\StructuredOutput;

class RunStructuredOutputInference implements CanRunExecution
{
    private StructuredOutputData $structuredOutputData;

    public function __construct(StructuredOutputData $data) {
        $this->structuredOutputData = $data;
    }

    #[\Override]
    public function run(Execution $execution) : Execution {
        $execution->data()->set('response', $this->makeInstructorResponse($execution)->response());
        return $execution;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeInstructorResponse(Execution $execution) : PendingStructuredOutput {
        return (new StructuredOutput)
            ->using($execution->get('case.preset'))
            ->with(
                messages: $this->structuredOutputData->messages,
                responseModel: $this->structuredOutputData->responseModel,
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
            )
            ->create();
    }
}
