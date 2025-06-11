<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Result\Result;

interface CanGenerateResponse
{
    public function makeResponse(InferenceResponse $response, ResponseModel $responseModel, OutputMode $mode) : Result;
}