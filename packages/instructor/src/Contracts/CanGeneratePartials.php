<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Generator;

interface CanGeneratePartials
{
    /**
     * @param Generator<PartialInferenceResponse> $stream
     * @param ResponseModel $responseModel
     * @return Generator<mixed>
     */
    public function getPartialResponses(Generator $stream, ResponseModel $responseModel) : Iterable;
}
