<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\PendingInference;

class InferenceProvider
{
    public function __construct(
        private LLMProvider $llmProvider,
        private CanMaterializeRequest $requestMaterializer,
        private CanHandleEvents $events,
        private ?HttpClient $httpClient = null,
    ) {}

    public function getInference(StructuredOutputExecution $execution): PendingInference {
        $request = $execution->request();
        $responseModel = $execution->responseModel();
        assert($responseModel !== null, 'Response model cannot be null');

        $inference = (new Inference(events: $this->events))
            ->withLLMProvider($this->llmProvider);
        if ($this->httpClient !== null) {
            $inference = $inference->withHttpClient($this->httpClient);
        }
        return $inference
            ->with(
                messages: $this->requestMaterializer->toMessages($execution),
                model: $request->model(),
                tools: $responseModel->toolCallSchema(),
                toolChoice: $responseModel->toolChoice(),
                responseFormat: $responseModel->responseFormat(),
                options: $request->options(),
                mode: $execution->outputMode(),
            )
            ->withResponseCachePolicy($execution->config()->responseCachePolicy())
            ->create();
    }
}
