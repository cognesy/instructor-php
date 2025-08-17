<?php declare(strict_types=1);

namespace Cognesy\Evals\Executors;

use Cognesy\Evals\Contracts\CanRunExecution;
use Cognesy\Evals\Execution;
use Cognesy\Evals\Executors\Data\InferenceData;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

class RunInference implements CanRunExecution
{
    private InferenceAdapter $inferenceAdapter;
    private InferenceData $inferenceData;

    public function __construct(InferenceData $data) {
        $this->inferenceAdapter = new InferenceAdapter();
        $this->inferenceData = $data;
    }

    public function run(Execution $execution) : Execution {
        $execution->data()->set('response', $this->makeInferenceResponse($execution));
        return $execution;
    }

    public function withDebugPreset(?string $preset) : self {
        $this->inferenceAdapter->withDebugPreset($preset);
        return $this;
    }

    public function wiretap(?callable $callback) : self {
        $this->inferenceAdapter->wiretap($callback);
        return $this;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeInferenceResponse(Execution $execution) : InferenceResponse {
        return $this->inferenceAdapter->callInferenceFor(
            preset: $execution->get('case.preset'),
            mode: $execution->get('case.mode'),
            isStreamed: $execution->get('case.isStreamed'),
            messages: $this->inferenceData->messages,
            evalSchema: $this->inferenceData->inferenceSchema(),
            maxTokens: $this->inferenceData->maxTokens,
        );
    }
}