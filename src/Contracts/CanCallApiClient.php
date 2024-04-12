<?php
namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Utils\Result;
use JetBrains\PhpStorm\Deprecated;

/**
 * Implemented by LLM providers that can call a function.
 */
#[Deprecated]
interface CanCallApiClient {
    public function callApiClient(
        array $messages,
        ResponseModel $responseModel,
        string $model,
        array $options
    ) : Result;
}
