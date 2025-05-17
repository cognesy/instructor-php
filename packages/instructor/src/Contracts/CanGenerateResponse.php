<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Result\Result;

interface CanGenerateResponse
{
    public function makeResponse(LLMResponse $response, ResponseModel $responseModel, OutputMode $mode) : Result;
}