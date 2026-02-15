<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\PendingInference;

class InferenceProvider
{
    public function __construct(
        private CanCreateInference|LLMProvider $llmProvider,
        private CanMaterializeRequest $requestMaterializer,
        private CanHandleEvents $events,
        private ?HttpClient $httpClient = null,
    ) {}

    public function getInference(StructuredOutputExecution $execution): PendingInference {
        $request = $execution->request();
        $responseModel = $execution->responseModel();
        assert($responseModel !== null, 'Response model cannot be null');

        return $this->inference()->create(new InferenceRequest(
            messages: $this->requestMaterializer->toMessages($execution),
            model: $request->model(),
            tools: $responseModel->toolCallSchema(),
            toolChoice: $responseModel->toolChoice(),
            responseFormat: $responseModel->responseFormat(),
            options: $request->options(),
            mode: $execution->outputMode(),
            responseCachePolicy: $execution->config()->responseCachePolicy(),
        ));
    }

    private function inference() : CanCreateInference {
        if ($this->llmProvider instanceof CanCreateInference) {
            return $this->llmProvider;
        }
        return $this->makeInference($this->llmProvider);
    }

    private function makeInference(LLMProvider $llmProvider) : CanCreateInference {
        $inference = (new Inference(events: $this->events))
            ->withLLMProvider($llmProvider);

        if ($this->httpClient === null) {
            return $inference;
        }

        return $inference->withHttpClient($this->httpClient);
    }
}
