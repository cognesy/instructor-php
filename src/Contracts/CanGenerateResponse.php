<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Extras\LLM\Data\LLMResponse;
use Cognesy\Instructor\Utils\Result\Result;

interface CanGenerateResponse
{
    public function makeResponse(LLMResponse $response, ResponseModel $responseModel) : Result;
}