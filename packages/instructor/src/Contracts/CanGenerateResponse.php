<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Polyglot\LLM\Data\InferenceResponse;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Result\Result;

interface CanGenerateResponse
{
    public function makeResponse(InferenceResponse $response, ResponseModel $responseModel, OutputMode $mode) : Result;
}