<?php

namespace Cognesy\Instructor\Extras\Task\Contracts;

use Cognesy\Instructor\Extras\Task\Enums\TaskStatus;

interface CanProcessInput
{
    public function inputs() : array;
    public function withContext(array $context) : static;
    public function status() : TaskStatus;
    public function onSuccess(callable $callback) : static;
    public function onFailure(callable $callback) : static;
    public function outputs() : array;
}
