<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Utils\Result\Result;

interface CanGenerateResponse
{
    public function makeResponse(ApiResponse $response, ResponseModel $responseModel) : Result;
}