<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\PendingInference;

class InferenceProvider
{
    public function __construct(
        private CanCreateInference $inference,
        private CanMaterializeRequest $requestMaterializer,
    ) {}

    public function getInference(StructuredOutputExecution $execution): PendingInference {
        $request = $execution->request();
        $responseModel = $execution->responseModel();
        assert($responseModel !== null, 'Response model cannot be null');

        return $this->inference->create(new InferenceRequest(
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
}
