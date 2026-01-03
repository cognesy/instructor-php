<?php declare(strict_types=1);

namespace Cognesy\Instructor\Deserialization\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Utils\Result\Result;

interface CanDeserializeResponse
{
    /** @param array<string, mixed> $data */
    public function deserialize(array $data, ResponseModel $responseModel) : Result;
}