<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Utils\Result;

interface CanHandleResponse
{
    public function handleResponse(ApiResponse $response, ResponseModel $responseModel) : Result;
}