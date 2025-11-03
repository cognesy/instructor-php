<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Contracts;

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
    public function makePartialResponses(Generator $stream, ResponseModel $responseModel) : Iterable;
}
