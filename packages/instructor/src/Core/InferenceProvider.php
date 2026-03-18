<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\PendingInference;

class InferenceProvider
{
    public function __construct(
        private CanCreateInference $inference,
        private CanMaterializeRequest $requestMaterializer,
    ) {}

    public function getInference(StructuredOutputExecution $execution): PendingInference {
        return $this->inference->create($this->requestMaterializer->toInferenceRequest($execution));
    }
}
