<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Core\ResponseModel;
use Cognesy\Instructor\Utils\Result;

interface CanHandleResponse
{
    public function toResponse(string $jsonData, ResponseModel $responseModel) : Result;
}