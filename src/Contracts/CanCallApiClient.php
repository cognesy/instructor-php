<?php
namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Utils\Result;

/**
 * Implemented by LLM providers that can call a function.
 */
interface CanCallApiClient {
    public function callApiClient(
        array $messages,
        ResponseModel $responseModel,
        string $model,
        array $options
    ) : Result;
}
