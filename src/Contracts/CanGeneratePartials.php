<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Extras\LLM\Data\PartialLLMApiResponse;
use Generator;

interface CanGeneratePartials
{
    /**
     * @param Generator<PartialLLMApiResponse> $stream
     * @param ResponseModel $responseModel
     * @return Generator<mixed>
     */
    public function getPartialResponses(Generator $stream, ResponseModel $responseModel) : Iterable;
}
