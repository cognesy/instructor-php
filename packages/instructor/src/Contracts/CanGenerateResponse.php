<?php declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Utils\Result\Result;

interface CanGenerateResponse
{
    public function makeResponse(
        InferenceResponse $response,
        ResponseModel $responseModel,
        OutputMode $mode,
        mixed $prebuiltValue = null,
    ) : Result;
}
