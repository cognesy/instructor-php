<?php
namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Utils\Result;

/**
 * Implemented by LLM providers that can call a function.
 */
interface CanCallFunction {
    public function callFunction(
        array $messages,
        string $functionName,
        array $functionSchema,
        string $model,
        array $options
    ) : Result;
}
