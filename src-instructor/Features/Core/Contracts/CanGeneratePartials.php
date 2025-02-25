<?php

namespace Cognesy\Instructor\Features\Core\Contracts;

use Cognesy\Instructor\Features\Core\Data\ResponseModel;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Generator;

interface CanGeneratePartials
{
    /**
     * @param Generator<\Cognesy\Polyglot\LLM\Data\PartialLLMResponse> $stream
     * @param ResponseModel $responseModel
     * @return Generator<mixed>
     */
    public function getPartialResponses(Generator $stream, ResponseModel $responseModel) : Iterable;
}
