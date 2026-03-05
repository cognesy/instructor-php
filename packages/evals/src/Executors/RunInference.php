<?php declare(strict_types=1);

namespace Cognesy\Evals\Executors;

use Cognesy\Evals\Contracts\CanRunExecution;
use Cognesy\Evals\Execution;
use Cognesy\Evals\Executors\Data\InferenceData;
use Cognesy\Http\Config\DebugConfig;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

class RunInference implements CanRunExecution
{
    private InferenceAdapter $inferenceAdapter;
    private InferenceData $inferenceData;

    public function __construct(InferenceData $data) {
        $this->inferenceAdapter = new InferenceAdapter();
        $this->inferenceData = $data;
    }

    #[\Override]
    public function run(Execution $execution) : Execution {
        $execution->data()->set('response', $this->makeInferenceResponse($execution));
        return $execution;
    }

    public function withDebugConfig(DebugConfig $debugConfig) : self {
        $this->inferenceAdapter->withDebugConfig($debugConfig);
        return $this;
    }

    /**
     * @param (callable(object):void)|null $callback
     */
    public function wiretap(?callable $callback) : self {
        $this->inferenceAdapter->wiretap($callback);
        return $this;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeInferenceResponse(Execution $execution) : InferenceResponse {
        $llmConfig = $execution->get('case.llmConfig');
        if (!$llmConfig instanceof LLMConfig) {
            throw new \InvalidArgumentException('Missing typed LLM config in case data.');
        }

        return $this->inferenceAdapter->callInferenceFor(
            llmConfig: $llmConfig,
            mode: $execution->get('case.mode'),
            isStreamed: $execution->get('case.isStreamed'),
            messages: $this->inferenceData->messages,
            evalSchema: $this->inferenceData->inferenceSchema(),
            maxTokens: $this->inferenceData->maxTokens,
        );
    }
}
