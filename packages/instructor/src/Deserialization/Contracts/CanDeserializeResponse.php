<?php declare(strict_types=1);

namespace Cognesy\Instructor\Deserialization\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Utils\Result\Result;

interface CanDeserializeResponse
{
    public function deserialize(string $text, ResponseModel $responseModel) : Result;
}