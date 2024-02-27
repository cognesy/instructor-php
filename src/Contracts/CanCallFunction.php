<?php
namespace Cognesy\Instructor\Contracts;

interface CanCallFunction {
    public function callFunction(array $messages, string $functionName, array $functionSchema, string $model, array $options) : string;
    public function response() : array;
}
