<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

interface CanMaterializeRequest
{
    public function toInferenceRequest(StructuredOutputExecution $execution): InferenceRequest;
}
