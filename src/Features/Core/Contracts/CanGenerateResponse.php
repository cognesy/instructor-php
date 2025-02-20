<?php

namespace Cognesy\Instructor\Features\Core\Contracts;

use Cognesy\Instructor\Features\Core\Data\ResponseModel;
use Cognesy\LLM\LLM\Data\LLMResponse;
use Cognesy\Utils\Result\Result;

interface CanGenerateResponse
{
    public function makeResponse(LLMResponse $response, ResponseModel $responseModel) : Result;
}