<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Utils\Result;

interface CanGenerateResponse
{
    public function makeResponse(ApiResponse $response, ResponseModel $responseModel) : Result;
}