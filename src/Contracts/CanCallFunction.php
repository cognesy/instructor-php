<?php
namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\LLMs\LLMResponse;

interface CanCallFunction {
    public function callFunction(array $messages, string $functionName, array $functionSchema, string $model, array $options) : LLMResponse;
}
