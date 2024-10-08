<?php

namespace Cognesy\Instructor\Features\Core\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Utils\Result\Result;

interface CanGenerateResponse
{
    public function makeResponse(LLMResponse $response, ResponseModel $responseModel) : Result;
}