<?php declare(strict_types=1);

namespace Cognesy\Instructor\Transformation\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Utils\Result\Result;

interface CanTransformResponse
{
    public function transform(mixed $data, ResponseModel $responseModel) : Result;
}