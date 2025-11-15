<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\GeneratorBased\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Generator;

interface CanGeneratePartials
{
    /**
     * @param Generator<PartialInferenceResponse> $stream
     * @param ResponseModel $responseModel
     * @return iterable<mixed>
     */
    public function makePartialResponses(Generator $stream, ResponseModel $responseModel) : iterable;
}
