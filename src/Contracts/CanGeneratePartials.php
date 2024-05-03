<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Generator;

interface CanGeneratePartials
{
    public function getPartialResponses(Generator $stream, ResponseModel $responseModel) : Iterable;
}
