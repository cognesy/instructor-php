<?php declare(strict_types=1);

namespace Cognesy\Evals\Executors;

use Cognesy\Evals\Contracts\CanRunExecution;
use Cognesy\Evals\Execution;
use Cognesy\Evals\Executors\Data\StructuredOutputData;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

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
        $config = (new StructuredOutputConfigBuilder())
            ->withMaxRetries($this->structuredOutputData->maxRetries)
            ->withToolName($this->structuredOutputData->toolName)
            ->withToolDescription($this->structuredOutputData->toolDescription)
            ->withRetryPrompt($this->structuredOutputData->retryPrompt)
            ->withOutputMode($execution->get('case.mode'))
            ->create();

        $request = new StructuredOutputRequest(
            messages: $this->structuredOutputData->messages,
            requestedSchema: $this->structuredOutputData->responseModel,
            system: $this->structuredOutputData->system,
            prompt: $this->structuredOutputData->prompt,
            examples: $this->structuredOutputData->examples,
            model: $this->structuredOutputData->model,
            options: [
                'max_tokens' => $this->structuredOutputData->maxTokens,
                'temperature' => $this->structuredOutputData->temperature,
                'stream' => $execution->get('case.isStreamed'),
            ],
        );

        return StructuredOutputRuntime::using(
            preset: $execution->get('case.preset'),
            structuredConfig: $config,
        )->create($request);
    }
}
